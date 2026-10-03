<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R1 regression (review 09-mod-season-a, hallazgo 1 [ALTA]):
 * checkpoint-after-commit race in both season pipelines.
 *
 * The old code ran the processor inside DB::transaction() and wrote the
 * checkpoint (season_transition_step + season_transition_data) in a separate
 * query AFTER the commit. If the process died in between (Wasmer Edge is
 * unstable — the codebase itself documents killed connections/timeouts), the
 * next chunk re-ran the processor and non-idempotent effects were applied
 * twice: ContractExpirationProcessor's stale free-agent cleanup DELETED the
 * free agents created by the first pass (permanent loss of real players),
 * PlayerDevelopmentProcessor applied development twice, reputation deltas
 * were duplicated, seasons_completed was incremented twice, the awards gala
 * posted twice, etc.
 *
 * The fix moves the checkpoint INSIDE the processor's transaction in both
 * pipelines. These tests pin that behaviour:
 *
 *  1. If the processor throws, NEITHER its work NOR the checkpoint persists
 *     (atomic rollback), and a re-run starts from a clean slate without
 *     duplicating anything (free agents intact, no double effects).
 *  2. The checkpoint UPDATE is issued while the processor's transaction is
 *     still open (observed via DB::transactionLevel), not after its commit —
 *     this is what fails on the pre-fix code.
 *  3. Resume semantics are preserved: a step whose checkpoint was committed
 *     is skipped by the next run.
 *  4. The same atomicity holds for SeasonSetupPipeline (global step index).
 */
class R1CheckpointAtomicTest extends TestCase
{
    use RefreshDatabase;

    protected Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::factory()->create();
        $this->game = Game::factory()->forTeam($team)->create([
            'season' => '2026',
            'base_season' => '2026',
            'current_date' => '2027-06-15',
            'season_transition_step' => null,
            'season_transition_data' => null,
        ]);
    }

    public function test_aborted_step_rolls_back_work_and_checkpoint_and_rerun_does_not_duplicate(): void
    {
        // A stale free agent (as left behind by a previous season) and a
        // contracted player of the user's team.
        $staleFreeAgent = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => null,
        ]);
        $contracted = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->game->team_id,
        ]);

        $pipeline = $this->closingPipelineWith(new R1ContractExpirationLikeProcessor(failAfterWork: true));

        try {
            $pipeline->run($this->spyGame(), -1, null, null);
            $this->fail('Expected the simulated crash to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('R1 simulated crash after processor work', $e->getMessage());
        }

        // Atomic rollback: the cleanup was rolled back (the free agent is
        // intact), the contracted player was untouched, and NO checkpoint was
        // written — the step is safe to re-run from a clean slate.
        $this->assertTrue(
            GamePlayer::whereKey($staleFreeAgent->id)->exists(),
            'Aborted pass must not delete the pre-existing free agent.',
        );
        $this->assertSame(
            $this->game->team_id,
            GamePlayer::whereKey($contracted->id)->value('team_id'),
            'Aborted pass must not free the contracted player.',
        );
        $this->assertNull($this->game->fresh()->season_transition_step, 'Aborted pass must not write a checkpoint.');
        $this->assertNull($this->game->fresh()->season_transition_data, 'Aborted pass must not persist the DTO.');

        // Re-run after the crash: exactly one committed pass, no duplication.
        $okProcessor = new R1ContractExpirationLikeProcessor(failAfterWork: false);
        $this->closingPipelineWith($okProcessor)->run($this->spyGame(), -1, null, null);

        $this->assertSame(1, $okProcessor->calls, 'The step must run exactly once on the re-run.');
        $this->assertFalse(
            GamePlayer::whereKey($staleFreeAgent->id)->exists(),
            'The committed pass runs the cleanup exactly once.',
        );
        $this->assertNull(
            GamePlayer::whereKey($contracted->id)->value('team_id'),
            'The contracted player becomes a free agent exactly once.',
        );
        $this->assertSame(0, $this->game->fresh()->season_transition_step);
        $this->assertNotNull($this->game->fresh()->season_transition_data);
    }

    public function test_checkpoint_is_written_inside_the_processor_transaction(): void
    {
        R1SpyGame::$checkpointTransactionLevels = [];
        $outerLevel = DB::transactionLevel();

        $this->closingPipelineWith(new R1ContractExpirationLikeProcessor())
            ->run($this->spyGame(), -1, null, null);

        $this->assertNotEmpty(
            R1SpyGame::$checkpointTransactionLevels,
            'The pipeline must write a checkpoint for the completed step.',
        );
        foreach (R1SpyGame::$checkpointTransactionLevels as $level) {
            // Pre-fix code wrote the checkpoint after the commit, i.e. at
            // $outerLevel. Post-fix it is written inside the processor's
            // transaction, i.e. at $outerLevel + 1.
            $this->assertSame(
                $outerLevel + 1,
                $level,
                'The checkpoint UPDATE must be issued inside the processor transaction, not after its commit.',
            );
        }
    }

    public function test_resume_skips_already_checkpointed_steps(): void
    {
        $processor = new R1ContractExpirationLikeProcessor();
        $pipeline = $this->closingPipelineWith($processor);

        $pipeline->run($this->spyGame(), -1, null, null);
        $this->assertSame(1, $processor->calls);
        $this->assertSame(0, $this->game->fresh()->season_transition_step);

        // Resume from the checkpointed step: the processor must not run again.
        $pipeline->run($this->spyGame(), 0, null, null);
        $this->assertSame(1, $processor->calls, 'A checkpointed step must be skipped on resume.');
    }

    public function test_setup_pipeline_checkpoint_is_atomic_too(): void
    {
        R1SpyGame::$checkpointTransactionLevels = [];
        $outerLevel = DB::transactionLevel();

        $pipeline = app(SeasonSetupPipeline::class);
        $ref = new \ReflectionProperty(SeasonSetupPipeline::class, 'processors');
        $ref->setAccessible(true);
        $processor = new R1SetupLikeProcessor();
        $ref->setValue($pipeline, [$processor]);

        $data = new SeasonTransitionData(
            oldSeason: '2026',
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        );

        // stepOffset 32 (closing pipeline processor count): the checkpoint
        // must record the GLOBAL step index.
        $result = $pipeline->run($this->spyGame(), $data, 32, -1, null);

        $this->assertSame(1, $processor->calls);
        $this->assertSame('2027', $result->newSeason);
        $this->assertSame(32, $this->game->fresh()->season_transition_step);
        $this->assertNotEmpty(R1SpyGame::$checkpointTransactionLevels);
        foreach (R1SpyGame::$checkpointTransactionLevels as $level) {
            $this->assertSame(
                $outerLevel + 1,
                $level,
                'Setup pipeline: checkpoint must be written inside the processor transaction.',
            );
        }
    }

    public function test_setup_pipeline_abort_rolls_back_work_and_checkpoint(): void
    {
        $pipeline = app(SeasonSetupPipeline::class);
        $ref = new \ReflectionProperty(SeasonSetupPipeline::class, 'processors');
        $ref->setAccessible(true);
        $ref->setValue($pipeline, [new R1SetupLikeProcessor(failAfterWork: true)]);

        $data = new SeasonTransitionData(
            oldSeason: '2026',
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        );

        try {
            $pipeline->run($this->spyGame(), $data, 32, -1, null);
            $this->fail('Expected the simulated crash to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('R1 simulated setup crash', $e->getMessage());
        }

        $this->assertNull($this->game->fresh()->season_transition_step, 'Aborted setup step must not write a checkpoint.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function closingPipelineWith(SeasonProcessor $processor): SeasonClosingPipeline
    {
        $pipeline = app(SeasonClosingPipeline::class);
        $ref = new \ReflectionProperty(SeasonClosingPipeline::class, 'processors');
        $ref->setAccessible(true);
        $ref->setValue($pipeline, [$processor]);

        return $pipeline;
    }

    /**
     * A Game instance that records the DB transaction depth at which the
     * checkpoint write happens — the observable that distinguishes "checkpoint
     * inside the transaction" from "checkpoint after the commit".
     */
    private function spyGame(): R1SpyGame
    {
        $spy = new R1SpyGame();
        $spy->setRawAttributes($this->game->fresh()->getAttributes(), true);
        $spy->exists = true;

        return $spy;
    }
}

/**
 * Mimics the non-idempotent pattern of ContractExpirationProcessor: first a
 * cleanup delete of stale free agents (ContractExpirationProcessor.php:47),
 * then turning contracted players into free agents. With the R1 fix, a crash
 * after this work rolls everything back; without it, the committed work would
 * be re-executed on resume (the cleanup deleting the just-created free
 * agents = permanent loss).
 */
class R1ContractExpirationLikeProcessor implements SeasonProcessor
{
    public int $calls = 0;

    public function __construct(private readonly bool $failAfterWork = false) {}

    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->calls++;

        GamePlayer::where('game_id', $game->id)->whereNull('team_id')->delete();
        GamePlayer::where('game_id', $game->id)->where('team_id', $game->team_id)->update(['team_id' => null]);

        if ($this->failAfterWork) {
            throw new \RuntimeException('R1 simulated crash after processor work');
        }

        return $data;
    }
}

/**
 * Trivial setup-pipeline stand-in that tags the DTO metadata.
 */
class R1SetupLikeProcessor implements SeasonProcessor
{
    public int $calls = 0;

    public function __construct(private readonly bool $failAfterWork = false) {}

    public function priority(): int
    {
        return 10;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->calls++;
        $data->setMetadata('r1_probe', true);

        if ($this->failAfterWork) {
            throw new \RuntimeException('R1 simulated setup crash');
        }

        return $data;
    }
}

class R1SpyGame extends Game
{
    protected $table = 'games';

    /** @var list<int> DB::transactionLevel() observed at each checkpoint write */
    public static array $checkpointTransactionLevels = [];

    public function updateQuietly(array $attributes = [], array $options = [])
    {
        if (array_key_exists('season_transition_step', $attributes)) {
            self::$checkpointTransactionLevels[] = DB::transactionLevel();
        }

        return parent::updateQuietly($attributes);
    }
}

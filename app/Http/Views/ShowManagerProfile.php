<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use App\Modules\Manager\Services\ManagerProfileService;
use App\Modules\Manager\Services\TrophySectionService;

class ShowManagerProfile
{
    public function __construct(
        private ManagerProfileService $profileService,
        private TrophySectionService $sectionService,
    ) {}

    public function __invoke(string $username)
    {
        $user = User::where('username', $username)
            ->where('is_profile_public', true)
            ->firstOrFail();

        $games = Game::with(['team', 'competition'])
            ->where('user_id', $user->id)
            ->get();

        $user->setRelation('games', $games);

        $trophies = $this->profileService->getTrophies($user);

        return view('profile.show', [
            'user' => $user,
            'trophies' => $trophies,
            'trophySections' => $this->sectionService->groupTrophies($trophies),
            'careerStats' => $this->profileService->getCareerStats($user),
        ]);
    }
}

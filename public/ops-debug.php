<?php
// Copiar tablas restantes sin orderBy
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
$NEW_URL = 'postgresql://neondb_owner:npg_Egr1cvCpktK6@ep-wispy-cake-b1wth7xu-pooler.c-5.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require';
$parts = parse_url($NEW_URL); parse_str($parts['query'] ?? '', $q);
Config::set('database.connections.newdb', ['driver'=>'pgsql','host'=>$parts['host'],'port'=>$parts['port']??5432,'database'=>ltrim($parts['path'],'/'),'username'=>$parts['user'],'password'=>$parts['pass'],'sslmode'=>$q['sslmode']??'require']);
DB::connection('newdb')->statement('SET search_path TO public');
$out = [];
foreach (['competition_teams','traffic_daily','traffic_hourly','traffic_visitor_days','visitor_heartbeats'] as $t) {
    try {
        DB::connection('newdb')->table($t)->delete();
        $n = 0;
        DB::connection('pgsql')->table($t)->chunk(500, function($rows) use ($t, &$n) {
            $data = array_map(fn($r)=>(array)$r, $rows->toArray());
            DB::connection('newdb')->table($t)->insert($data);
            $n += count($data);
        });
        $out[] = "$t: $n OK";
    } catch (Throwable $e) { $out[] = "$t FAIL: ".substr($e->getMessage(),0,100); }
}
echo implode("\n",$out)."\n";

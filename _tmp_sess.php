<?php
echo \Illuminate\Support\Facades\Schema::hasTable('sessions')
    ? 'sessions table: EXISTS, rows=' . \Illuminate\Support\Facades\DB::table('sessions')->count() . PHP_EOL
    : 'sessions table: MISSING' . PHP_EOL;

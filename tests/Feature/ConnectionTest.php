<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    #[Test]
    public function database_connection_is_working(): void
    {
        $result = DB::select('SELECT 1');
        $this->assertEquals(1, $result[0]->{'1'} ?? null);
    }

    #[Test]
    public function redis_connection_is_working(): void
    {
        Redis::set('connection_test', 'ok');
        $this->assertEquals('ok', Redis::get('connection_test'));
    }
}

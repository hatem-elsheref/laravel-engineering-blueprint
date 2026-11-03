<?php

use Best\Blueprint\Tests\MysqlIntegration\MysqlTestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(MysqlTestCase::class, RefreshDatabase::class);

it('rolls back a transaction on mysql', function (): void {
    Schema::create('ledger', function ($table) {
        $table->id();
        $table->unsignedInteger('amount');
    });

    DB::beginTransaction();
    DB::table('ledger')->insert(['amount' => 100]);
    DB::rollBack();

    expect(DB::table('ledger')->count())->toBe(0);
});

it('creates a unique index on mysql', function (): void {
    Schema::create('skus', function ($table) {
        $table->id();
        $table->string('code');
        $table->unique('code');
    });

    DB::table('skus')->insert(['code' => 'A1']);

    expect(fn () => DB::table('skus')->insert(['code' => 'A1']))
        ->toThrow(QueryException::class);
});

it('enforces a foreign key on mysql', function (): void {
    Schema::create('owners', function ($table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('pets', function ($table) {
        $table->id();
        $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
        $table->string('name');
    });

    DB::table('owners')->insert(['id' => 1, 'name' => 'Ada']);
    DB::table('pets')->insert(['owner_id' => 1, 'name' => 'Nox']);

    DB::table('owners')->where('id', 1)->delete();

    expect(DB::table('pets')->count())->toBe(0);

    expect(fn () => DB::table('pets')->insert(['owner_id' => 99, 'name' => 'Ghost']))
        ->toThrow(QueryException::class);
});

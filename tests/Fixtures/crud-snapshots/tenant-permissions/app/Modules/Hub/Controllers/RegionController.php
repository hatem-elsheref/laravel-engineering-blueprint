<?php

declare(strict_types=1);

namespace App\Modules\Hub\Controllers;

use App\Modules\Hub\Actions\CreateRegion;
use App\Modules\Hub\Actions\DeleteRegion;
use App\Modules\Hub\Actions\UpdateRegion;
use App\Modules\Hub\Data\RegionData;
use App\Modules\Hub\Models\Region;
use App\Modules\Hub\Queries\ListRegions;
use App\Modules\Hub\Requests\StoreRegionRequest;
use App\Modules\Hub\Requests\UpdateRegionRequest;
use App\Modules\Hub\Resources\RegionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class RegionController
{
    public function index(ListRegions $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Region::class);

        return RegionResource::collection($query->handle());
    }

    public function store(StoreRegionRequest $request, CreateRegion $action): JsonResponse
    {
        $region = $action->handle(RegionData::fromRequest($request));

        return (new RegionResource($region))->response()->setStatusCode(201);
    }

    public function show(Region $region): RegionResource
    {
        Gate::authorize('view', $region);

        return new RegionResource($region);
    }

    public function update(UpdateRegionRequest $request, Region $region, UpdateRegion $action): RegionResource
    {
        return new RegionResource($action->handle($region, RegionData::fromRequest($request)));
    }

    public function destroy(Region $region, DeleteRegion $action): Response
    {
        Gate::authorize('delete', $region);

        $action->handle($region);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Http\Resources\Api\V1\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(protected AccountService $accounts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->accounts->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return AccountResource::collection($result['accounts'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->accounts->create($request->payload());
        $account->setAttribute('usage_count', 0);

        return (new AccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account): AccountResource
    {
        $account->setAttribute('usage_count', $this->accounts->usageCount($account));

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $account = $this->accounts->update($account, $request->payload());
        $account->setAttribute('usage_count', $this->accounts->usageCount($account));

        return new AccountResource($account);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accounts->delete($account);

        return response()->json(['message' => 'Account deleted.']);
    }
}

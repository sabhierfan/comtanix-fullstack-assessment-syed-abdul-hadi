<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignQuoteRequestRequest;
use App\Http\Requests\ListQuoteRequestsRequest;
use App\Http\Requests\UpdateQuoteStatusRequest;
use App\Http\Resources\QuoteRequestResource;
use App\Models\QuoteRequest;
use App\Queries\QuoteRequestListQuery;
use App\Services\QuoteRequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuoteRequestController extends Controller
{
    public function __construct(private readonly QuoteRequestWorkflow $workflow) {}

    public function index(ListQuoteRequestsRequest $request, QuoteRequestListQuery $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', QuoteRequest::class);

        return QuoteRequestResource::collection($query->paginate($request->validated()));
    }

    public function show(QuoteRequest $quoteRequest): QuoteRequestResource
    {
        Gate::authorize('view', $quoteRequest);

        return new QuoteRequestResource($quoteRequest->load('assignee'));
    }

    public function updateStatus(UpdateQuoteStatusRequest $request, QuoteRequest $quoteRequest): QuoteRequestResource
    {
        $updated = $this->workflow->changeStatus($quoteRequest, $request->status(), $request->user());

        return new QuoteRequestResource($updated->load('assignee'));
    }

    public function assign(AssignQuoteRequestRequest $request, QuoteRequest $quoteRequest): QuoteRequestResource
    {
        $updated = $this->workflow->assign($quoteRequest, $request->assignee(), $request->user());

        return new QuoteRequestResource($updated->load('assignee'));
    }

    public function destroy(Request $request, QuoteRequest $quoteRequest): Response
    {
        Gate::authorize('delete', $quoteRequest);

        $this->workflow->delete($quoteRequest, $request->user());

        return response()->noContent();
    }
}

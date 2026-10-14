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

/**
 * Staff endpoints for quote requests.
 *
 * This controller is kept "thin" on purpose. It does not contain business rules.
 * Each method only:
 *   1. gets already-validated input (the Form Request classes check it first),
 *   2. checks permission (Gate / Policy),
 *   3. calls the workflow or query class that does the real work,
 *   4. returns a Resource (which decides what the JSON looks like).
 */
class QuoteRequestController extends Controller
{
    public function __construct(private readonly QuoteRequestWorkflow $workflow) {}

    /** GET /api/quote-requests: the list, with search, filters, sort and pages. */
    public function index(ListQuoteRequestsRequest $request, QuoteRequestListQuery $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', QuoteRequest::class);

        return QuoteRequestResource::collection($query->paginate($request->validated()));
    }

    /** GET /api/quote-requests/{id}: one request with its assignee. */
    public function show(QuoteRequest $quoteRequest): QuoteRequestResource
    {
        Gate::authorize('view', $quoteRequest);

        return new QuoteRequestResource($quoteRequest->load('assignee'));
    }

    /**
     * PATCH /api/quote-requests/{id}/status
     * Permission + input are checked in UpdateQuoteStatusRequest before we get here.
     * The workflow checks the status rules and returns 422 if the move is not allowed.
     */
    public function updateStatus(UpdateQuoteStatusRequest $request, QuoteRequest $quoteRequest): QuoteRequestResource
    {
        $updated = $this->workflow->changeStatus($quoteRequest, $request->status(), $request->user());

        return new QuoteRequestResource($updated->load('assignee'));
    }

    /** PATCH /api/quote-requests/{id}/assign (admin only, checked in AssignQuoteRequestRequest). */
    public function assign(AssignQuoteRequestRequest $request, QuoteRequest $quoteRequest): QuoteRequestResource
    {
        $updated = $this->workflow->assign($quoteRequest, $request->assignee(), $request->user());

        return new QuoteRequestResource($updated->load('assignee'));
    }

    /** DELETE /api/quote-requests/{id}: soft delete, admin only. Returns 204 (no body). */
    public function destroy(Request $request, QuoteRequest $quoteRequest): Response
    {
        Gate::authorize('delete', $quoteRequest);

        $this->workflow->delete($quoteRequest, $request->user());

        return response()->noContent();
    }
}

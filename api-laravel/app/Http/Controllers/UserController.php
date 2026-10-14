<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Users for the assignee filter and the assign dropdown.
     */
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->orderBy('name')->get(['id', 'name', 'email', 'role'])
        );
    }
}

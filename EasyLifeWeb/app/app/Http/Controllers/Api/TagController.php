<?php

namespace App\Http\Controllers\Api;

use App\Models\Tag;

class TagController extends ResourceController
{
    protected string $model = Tag::class;
    protected string $orderBy = 'name';
}

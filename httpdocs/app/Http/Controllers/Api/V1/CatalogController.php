<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        return response()->json([
            'case_types' => config('sanad.case_types', []),
            'community_categories' => config('sanad.community_categories', []),
            'group_age_categories' => config('sanad.group_age_categories', []),
            'group_disorder_tags' => config('sanad.group_disorder_tags', []),
            'task_templates' => config('sanad.task_templates', []),
            'pre_session_questions' => config('sanad.pre_session_questions', []),
        ]);
    }
}

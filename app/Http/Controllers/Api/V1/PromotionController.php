<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Promotion;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Auth;

class PromotionController extends Controller
{
    use HttpResponses;

    public function index()
    {
        $user = Auth::user();

        $admin = $user->parent->parent->agent_id;

        $data = Promotion::where('admin_id', $admin)->get();
        // $data = Promotion::get();

        return $this->success($data, 'Promotion retrieved successfully.');
    }

    public function show($id)
    {
        $promotion = Promotion::find($id);

        return $this->success($promotion);
    }
}

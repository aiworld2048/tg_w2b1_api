<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserGuideController extends Controller
{
    /**
     * Display the admin user guide rendered from Markdown.
     */
    public function __invoke()
    {
        $path = base_path('docs/admin-user-guide.md');

        abort_unless(File::exists($path), 404, 'User guide not found.');

        $markdown = File::get($path);
        $html = Str::markdown($markdown);

        return view('admin.user_guide', [
            'content' => $html,
        ]);
    }
}


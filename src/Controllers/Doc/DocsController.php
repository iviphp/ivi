<?php

namespace App\Controllers\Doc;

use App\Controllers\Controller;
use Ivi\Http\Request;

class DocsController extends Controller
{
    private string $path = 'docs.';

    public function doc(Request $request)
    {
        $title = 'Documentation';

        return $this->view($this->path . 'home', compact('title', 'request'));
    }
}

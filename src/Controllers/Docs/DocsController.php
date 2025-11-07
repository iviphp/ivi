<?php

namespace App\Controllers\Docs;

use App\Controllers\Controller;
use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;

final class DocsController extends Controller
{
    public function index(Request $request): HtmlResponse
    {
        return $this->view('docs.home', [
            'title'  => 'Documentation — ivi.php',
            'styles' => '<link rel="stylesheet" href="' . asset('assets/css/docs.css') . '">',
            'scripts' => '<script src="' . asset('assets/js/docs.js') . '" defer></script>',
        ], $request);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Home;

use App\Controllers\Controller;
use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;

final class HomeController extends Controller
{
    private string $path = 'welcome.';

    public function home(Request $request): HtmlResponse
    {
        $data = [
            'title'   => 'Welcome to ivi.php',
            'styles'  => '<link rel="stylesheet" href="' . asset('assets/css/welcome.css') . '">',
            'scripts' => '<script src="' . asset('assets/js/welcome.js') . '" defer></script>',
        ];

        return $this->view($this->path . 'home', $data, $request);
    }
}

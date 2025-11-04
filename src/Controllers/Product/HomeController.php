<?php

declare(strict_types=1);

namespace App\Controllers\Product;

use App\Controllers\Controller;
use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;

final class HomeController extends Controller
{
    private string $path = 'product.';

    public function home(Request $request): HtmlResponse
    {
        $data = [
            'title' => 'Welcome to ivi.php',
            'products' => [
                ['id' => 1, 'name' => 'Laptop'],
                ['id' => 2, 'name' => 'Phone'],
            ],
        ];

        return $this->view($this->path . 'home', $data, $request);
    }
}

<?php

namespace Modules\Market\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

/**
 * -----------------------------------------------------------------------------
 * HomeController (Market/Core Module)
 * -----------------------------------------------------------------------------
 *
 * Handles HTTP requests for the **Market/Core** module’s home page.
 * This controller is responsible for rendering the main landing page of the
 * marketplace, including setting the proper HTML title and providing the
 * required data to the view layer.
 *
 * ## Responsibilities
 * - Retrieve the marketplace title from configuration (`market.title`).
 * - Set the page title for the HTML layout.
 * - Render the view `market::home` with all necessary data.
 *
 * ## Design Notes
 * - Uses `cfg()` helper to fetch configuration safely, with a fallback default.
 * - Extends the base `App\Controllers\Controller` for access to shared methods
 *   such as `setPageTitle()` and `view()`.
 * - Returns a typed `HtmlResponse` to ensure consistency across all Ivi modules.
 *
 * @package  Market\Core\Infra\Http\Controllers
 * @category Controllers
 * @version  1.0.0
 * @since    Ivi Framework v1.1
 */
final class HomeController extends Controller
{
    /**
     * Display the Market home page.
     *
     * This method retrieves the marketplace title from configuration,
     * sets it in the layout, and renders the associated view with
     * the provided context.
     *
     * @return HtmlResponse The rendered HTML response for the home page.
     */
    public function index(): HtmlResponse
    {
        $title = (string) (cfg('market.title', 'Softadastra Market') ?: 'Softadastra Market');
        $this->setPageTitle($title);

        // favicon spécifique au module
        $favicon = module_asset('Market/Core', 'softadastra-market.png');
        $css     = module_asset('Market/Core', 'assets/css/style.css');

        return $this->view('market::home', [
            'title'   => 'Softadastra Market',
            'favicon' => $favicon,
            'css'     => $css,
        ]);
    }
}

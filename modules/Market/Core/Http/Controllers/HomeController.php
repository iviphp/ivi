<?php

namespace Modules\Market\Core\Http\Controllers;

use App\Controllers\Controller;
use Google\Service\AIPlatformNotebooks\DefaultValues;
use Ivi\Core\Cache\Cache;
use Ivi\Http\HtmlResponse;
use Ivi\Core\Services\GoogleService;

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
    private GoogleService $google;

    public function __construct()
    {
        // Récupère la configuration Google via le helper global
        $config = config_value('google');

        if (!$config || !is_array($config)) {
            throw new \RuntimeException(
                "Google configuration not found. " .
                    "Ensure config/google.php exists and returns an array."
            );
        }

        $this->google = new GoogleService($config);
    }
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
        $cache = Cache::getInstance();

        $cacheKey = 'home_message';

        $message = $cache->remember($cacheKey, 3600, function () {
            return "Hello ! (generated at " . date('H:i:s') . " | fresh)";
        });

        // favicon spécifique au module
        $favicon = module_asset('Market/Core', 'softadastra-market.png');
        $css     = module_asset('Market/Core', 'assets/css/style.css');

        return $this->view('market::home', [
            'title'   => 'Market Title',
            'favicon' => $favicon,
            'styles'     => $css,
            'message' => $message
        ]);
    }

    public function viewCache()
    {
        $cache = Cache::getInstance();

        $keys = [
            'key_message' => "Hi! (generated at " . date('H:i:s') . " | home)",
            'welcome_message' => "Welcome! (generated at " . date('H:i:s') . " | welcome)",
            'footer_message' => "Footer info (generated at " . date('H:i:s') . " | footer"
        ];

        $messages = [];
        foreach ($keys as $key => $defaultValue) {
            $messages[$key] = $cache->remember($key, 3600, function () use ($defaultValue) {
                return $defaultValue;
            });

            if ($cache->get($key) !== null) {
                $messages[$key] .= " [from cache]";
            }
        }

        $allKeys = $cache->listKeys();

        dd([
            'keys_in_cache' => $allKeys,
            'messages' => $messages
        ]);
    }
}

<?php

namespace App\Attributes;

use App\Enums\Theme;
use Attribute;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class UsesTheme
{
    public function __construct(public readonly Theme $theme) {}

    /**
     * Resolve the theme for a route: method attribute, then class attribute,
     * then the site-wide default.
     */
    public static function forRoute(?Route $route): Theme
    {
        $controller = $route?->getController();

        if (! is_object($controller)) {
            return Theme::default();
        }

        $method = self::actionMethod($route);

        if ($method === null || ! method_exists($controller, $method)) {
            return Theme::default();
        }

        $methodTheme = self::fromAttributes((new ReflectionMethod($controller, $method))->getAttributes(self::class));

        if ($methodTheme !== null) {
            return $methodTheme;
        }

        $classTheme = self::fromAttributes((new ReflectionClass($controller))->getAttributes(self::class));

        if ($classTheme !== null) {
            return $classTheme;
        }

        return Theme::default();
    }

    /**
     * The controller method the route dispatches to.
     *
     * `Route::getActionMethod()` splits the action name on "@", but a bare
     * class-string action (`Route::get('/', HomeController::class)`, as used
     * by every single-action controller in this app) has no "@" — the action
     * name is just the class name, so the split returns it whole instead of
     * "__invoke". Reading the `uses` string ("Controller@method") directly
     * avoids that.
     */
    private static function actionMethod(Route $route): ?string
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return null;
        }

        return Str::afterLast($uses, '@');
    }

    /**
     * @param  array<int, \ReflectionAttribute<self>>  $attributes
     */
    private static function fromAttributes(array $attributes): ?Theme
    {
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->theme;
    }
}

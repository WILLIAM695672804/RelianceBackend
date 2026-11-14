<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;
use ReflectionClass;
use ReflectionMethod;

class GenerateApiContract extends Command
{
    protected $signature = 'api:generate-contract';
    protected $description = 'Generate API Contract Markdown for Flutter';

    public function handle()
    {
        $this->info("Parsing routes...");
        $routes = Route::getRoutes()->getRoutes();
        
        $modules = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $action = $route->getAction();
            $controllerAction = $action['uses'] ?? null;
            if ($controllerAction instanceof \Closure || !$controllerAction) {
                continue;
            }
            
            list($controller, $method) = explode('@', $controllerAction);
            
            preg_match('/App\\\\Modules\\\\([^\\\\]+)\\\\/', $controller, $matches);
            $moduleName = $matches[1] ?? 'Core';
            
            $middlewares = $route->middleware();
            if (!is_array($middlewares)) $middlewares = [$middlewares];
            
            $requestRules = [];
            
            try {
                $refClass = new ReflectionClass($controller);
                if ($refClass->hasMethod($method)) {
                    $refMethod = $refClass->getMethod($method);
                    foreach ($refMethod->getParameters() as $param) {
                        $type = $param->getType();
                        if ($type && !$type->isBuiltin()) {
                            $className = $type->getName();
                            if (is_subclass_of($className, 'Illuminate\Foundation\Http\FormRequest')) {
                                try {
                                    $reqInstance = new $className();
                                    if (method_exists($reqInstance, 'rules')) {
                                        $requestRules = $reqInstance->rules();
                                    }
                                } catch (\Exception $e) {
                                    $requestRules = "Erreur d'extraction (" . class_basename($className) . ")";
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $auth = in_array('auth:sanctum', $middlewares) ? 'Sanctum' : 'Public';
            
            $roles = [];
            foreach ($middlewares as $mw) {
                if (str_starts_with((string)$mw, 'role:')) {
                    $roles[] = str_replace('role:', '', $mw);
                }
                if (str_starts_with((string)$mw, 'Spatie\Permission\Middleware\RoleMiddleware:')) {
                    $roles[] = str_replace('Spatie\Permission\Middleware\RoleMiddleware:', '', $mw);
                }
            }

            $modules[$moduleName][] = [
                'uri' => $uri,
                'methods' => implode('|', $route->methods()),
                'controller' => class_basename($controller),
                'method' => $method,
                'middleware' => implode(', ', $middlewares),
                'auth' => $auth,
                'roles' => implode(', ', $roles) ?: 'Aucun',
                'rules' => $requestRules,
            ];
        }

        $md = "# 📡 Contrat API Officiel - Reliance\n\n";
        $md .= "> Document généré automatiquement à partir du code source Laravel.\n> Aucune donnée inventée. Si non défini, indiqué explicitement.\n\n";
        
        foreach ($modules as $module => $endpoints) {
            $md .= "## 📦 Module : $module\n\n";
            foreach ($endpoints as $ep) {
                $md .= "### Endpoint : `" . $ep['methods'] . " /" . $ep['uri'] . "`\n\n";
                $md .= "#### 1. Informations générales\n";
                $md .= "| Élément | Valeur |\n";
                $md .= "| --- | --- |\n";
                $md .= "| Module | $module |\n";
                $md .= "| URI | /" . $ep['uri'] . " |\n";
                $md .= "| Méthode HTTP | " . $ep['methods'] . " |\n";
                $md .= "| Version API | v1 |\n";
                $md .= "| Controller | " . $ep['controller'] . " |\n";
                $md .= "| Méthode Controller | " . $ep['method'] . " |\n";
                $md .= "| Middleware | " . $ep['middleware'] . " |\n";
                $md .= "| Authentification | " . $ep['auth'] . " |\n";
                $md .= "| Rôles autorisés | " . $ep['roles'] . " |\n\n";
                
                $md .= "#### 4. Validation Laravel (FormRequest)\n";
                if (is_array($ep['rules']) && count($ep['rules']) > 0) {
                     $md .= "| Paramètre | Règles exactes |\n";
                     $md .= "| --- | --- |\n";
                     foreach ($ep['rules'] as $field => $ruleset) {
                         $rulesStr = is_array($ruleset) ? implode(' | ', $ruleset) : (string)$ruleset;
                         $rulesStr = str_replace('|', '\|', $rulesStr);
                         if(is_object($ruleset)) $rulesStr = get_class($ruleset);
                         $md .= "| `$field` | `$rulesStr` |\n";
                     }
                } else if (is_string($ep['rules'])) {
                     $md .= $ep['rules'] . "\n";
                } else {
                     $md .= "Non défini dans le code.\n";
                }
                $md .= "\n---\n\n";
            }
        }
        
        $this->info("Parsing Enums...");
        $md .= "# ENUMS\n\n";
        $finder = new Finder();
        $finder->files()->in(app_path())->name('*.php')->contains('enum ');
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            if (preg_match('/enum\s+([A-Za-z0-9_]+)/', $content, $m)) {
                $md .= "### `" . $m[1] . "`\n";
                // Regex to find cases
                preg_match_all('/case\s+([A-Za-z0-9_]+)\s*(?:=\s*[\'"]?([^\'";]+)[\'"]?)?/', $content, $cases, PREG_SET_ORDER);
                foreach ($cases as $case) {
                    $val = isset($case[2]) ? $case[2] : $case[1];
                    $md .= "- `" . $case[1] . "` = `" . $val . "`\n";
                }
                $md .= "\n";
            }
        }
        
        $this->info("Parsing Models...");
        $md .= "# MODÈLES\n\n";
        $finder = new Finder();
        $finder->files()->in(app_path())->name('*.php')->contains('extends Model');
        foreach ($finder as $file) {
            // Get FQCN
            $content = file_get_contents($file->getRealPath());
            if (preg_match('/namespace\s+([^;]+);/', $content, $nsM) && preg_match('/class\s+([A-Za-z0-9_]+)\s+extends/', $content, $clM)) {
                $class = $nsM[1] . '\\' . $clM[1];
                if (class_exists($class)) {
                    $ref = new ReflectionClass($class);
                    if (!$ref->isAbstract()) {
                        try {
                            $instance = new $class;
                            $md .= "### `" . $clM[1] . "`\n";
                            $md .= "- **Table SQL** : `" . $instance->getTable() . "`\n";
                            $md .= "- **Fillable** : `" . implode(', ', $instance->getFillable()) . "`\n";
                            $md .= "- **Hidden** : `" . implode(', ', $instance->getHidden()) . "`\n";
                            $md .= "- **Casts** : \n";
                            foreach ($instance->getCasts() as $field => $type) {
                                $md .= "  - `$field` => `$type`\n";
                            }
                            $md .= "\n";
                        } catch (\Exception $e) {
                            $md .= "### `" . $clM[1] . "`\nErreur d'instanciation.\n\n";
                        }
                    }
                }
            }
        }

        file_put_contents(base_path('api_endpoints.md'), $md);
        $this->info('API Contract generated at api_endpoints.md');
    }
}

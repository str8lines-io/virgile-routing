# Virgile : An agnostic PHP Router

[![build](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml/badge.svg?job=build)](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml)
[![static analysis](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml/badge.svg?job=static%20analysis)](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml)
[![coverage](https://codecov.io/gh/str8lines-io/virgile-routing/graph/badge.svg)](https://codecov.io/gh/str8lines-io/virgile-routing)
[![style check](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml/badge.svg?job=style%20check)](https://github.com/str8lines-io/virgile-routing/actions/workflows/ci.yml)

[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/github/license/str8lines-io/virgile-routing)](https://github.com/str8lines-io/virgile-routing/blob/main/LICENSE)

A lightweight, high-performance, and strictly decoupled routing engine for PHP, designed with immutability and strict data encapsulation.

## Features

- **Protocol Agnostic**: Completely decoupled from HTTP, CLI, or any specific protocol via the `SignatureInterface`.
- **Strict Encapsulation**: Internal data structures are protected using read-only interfaces (`RouterNodeInterface`).
- **Immutability by Design**: Routes are immutable objects ensuring thread-safety and predictability.
- **Hierarchical Grouping**: Group routes with shared prefixes, controllers, and middlewares using clean closure scopes.
- **Zero Magic**: Simple and transparent structure without over-engineered dependency injection containers or hidden magic chains.

## How it works (The Core Signature)

Since `virgile/routing` is protocol-agnostic, you need to implement `SignatureInterface` to define how your application routes requests (whether HTTP, Console, or anything else).

> ⚠️ **Important Note**: 
> If you plan to use this Router as is, **it does not include any HTTP logic out of the box**.  
> To use the Quick Start examples below, you *must* provide an implementation of `SignatureInterface`.

To make your life easier, here is a fully functional **`HttpSignature`** implementation. **All the code examples in the Quick Start section below rely on this exact signature class** (handling HTTP methods, static paths, and dynamic parameters like `{id}`). 

You can copy this implementation directly into your project or adapt it to your own needs:

<details>
<summary><b>Click here to view a full implementation</b></summary>

```php
<?php

declare(strict_types=1);

use Exception;
use InvalidArgumentException;
use Virgile\Routing\SignatureInterface;

/**
 * Signature of an Http request.
 */
final class HttpSignature implements SignatureInterface
{
    /**
     * Defines if the route is dynamic.
     * @var bool $isDynamic
     */
    private bool $isDynamic;

    /**
     * The request uri
     * @var string $path
     */
    private string $path;

    /**
     * The Http method
     * @var string $method
     */
    private string $method;

    /**
     * The normalized request uri
     * @var string $normalizedPath
     */
    private string $normalizedPath;

    /**
     * Array containing the Http method in index 0,
     * and the segments from request uri in the following indexes.
     * @var array<string> $indexingPath
     */
    private array $indexingPath;

    /**
     * The request uri with wildcards replacing the dynamic parameters
     * @var string $compiledPath
     */
    private string $compiledPath;

    /**
     * Uses the compiled path to define the regex which allows dynamic parameters recognition.
     * null if route isn't dynamic.
     * @var ?string $regex
     */
    private ?string $regex = null;

    /**
     * List of HTTP methods
     * @var array<string>
     */
	private array $methods = ["GET", "HEAD", "POST", "PUT", "DELETE", "CONNECT", "OPTIONS", "TRACE", "PATCH"];
    
    /**
     * Pattern used to recognise variables in the request uri.
     * Supports {}, : and [] syntax.
     * @var string $varReconPattern 
     */
    private string $varReconPattern = '#[:\{\[][a-zA-Z0-9_]+[}\}\]]?#'; // looks for {}, : and [] patterns within a path
    
    /**
     * Pattern used to gather variables in the request uri.
     * Supports {}, : and [] syntax.
     * @var string $extractionPattern
     */
    private string $extractionPattern = '#(?<=:)[a-zA-Z0-9_]+|(?<=[{\[])[a-zA-Z0-9_]+(?=[}\])])#';

    /**
     * HttpSignature constructor checks params validity
     * 
     * @param string $path
     * @param string $method
     * @throws InvalidArgumentException
     */
    public function __construct(string $path, string $method = "GET")
    {
        if(in_array(strtoupper($path), $this->methods)) throw new InvalidArgumentException('$path must NOT be an HTTP method');
        $this->path = $path;
        $this->method = $method;

        $this->normalizedPath = $this->normalizePath($this->path);
        $this->isDynamic = (bool)preg_match($this->varReconPattern, $this->normalizedPath);
        if($this->isDynamic)
        { 
            $this->compiledPath = preg_replace([
                    '#:([a-zA-Z0-9_]+)#',   // replacement for :id patterns
                    '#{([a-zA-Z0-9_]+)}#',  // replacement for {id} patterns
                    '#\[([a-zA-Z0-9_]+)\]#'   // replacement for [id] patterns
                ], 
                self::DYN_WILDCARD, 
                $this->normalizedPath
            );
            $this->regex = '#^' . $this->compiledPath . '$#';
        }
        
        $explodedPath = ($this->normalizedPath === '') ? [] : explode('/', $this->normalizedPath);
        foreach($explodedPath as &$segment)
        {
            if($this->isDynamic() && preg_match($this->varReconPattern, $segment)){ 
                $segment = self::DYN_WILDCARD; // Equals to '([^/]+)'
            }
        }

        unset($segment);
        array_unshift($explodedPath, $this->normalizeMethod($method));
        $this->indexingPath = $explodedPath;
    }

    /** 
     * Array containing the Http method in index 0,
     * and the segments from request uri in the following indexes. 
     * Leads to a node of the router's routes tree.
     *
     * @return array<string>
     */
	public function getIndexingPath(): array
    {
        return $this->indexingPath;
    }

    /**
     * Array containing Http method in index 0 and the raw request uri in index 1
     * 
     * @return mixed
     */
    public function getRaw(): mixed
    {
        return [ 
            $this->method,
            $this->path
        ];
    }
    
    /**
     * Defines if the route is dynamic.
     * 
     * @return bool
     */
    public function isDynamic(): bool { return $this->isDynamic; }

    /**
     * Returns parameters used in dynamic segments by comparing an Http Signature against a route Signature.
     *
     * @param SignatureInterface $signature
     * @return array<string, string>
     */
    public function extractParams(SignatureInterface $signature): array 
    {
        if(!$this->isDynamic) return [];
        
        $rawPath = $signature->getRaw()[1];
        $normalizedPath = $this->normalizePath($rawPath);
        if(!preg_match($this->regex, $normalizedPath, $valueMatches)) return []; // stocks the following in $valueMatches => ['/api/users/42/posts/109', '42', '109']
        array_shift($valueMatches);  // $valueMatches becommes like this => ['42', '109']
        
        preg_match_all($this->extractionPattern, $this->normalizedPath, $keyMatches);
        $keys = $keyMatches[0] ?? []; // preg_match_all returns an array of matching arrays
        if(count($keys) !== count($valueMatches)) throw new Exception("Routing Error : parameters extraction mismatch while resolving $normalizedPath");
        return array_combine($keys, $valueMatches);
    }

    /** 
     * Normalizes the request uri, eliminating left and right slashes
     * 
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $path = ltrim(rtrim($path, '/'), '/');
        return $path;
    }

    /** 
     * Normalizes the http method using upper cases
     * 
     * @param string $method
     * @return string
     */
	private function normalizeMethod(string $method): string
	{
		$method = strtoupper($method);
		if(!in_array($method, $this->methods)) throw new \Exception("Route error : http method not supported.");
		return $method;
	}

    /**
     * Shortcut to create a get signature
     * @param string $path
     * @return self
     */
    public static function get(string $path)    :   self { return new self($path, "GET");       }
    
    /**
     * Shortcut to create a post signature
     * @param string $path
     * @return self
     */
    public static function post(string $path)   :   self { return new self($path, "POST");      }
    
    /**
     * Shortcut to create a put signature
     * @param string $path
     * @return self
     */
    public static function put(string $path)    :   self { return new self($path, "PUT");       }
    
    /**
     * Shortcut to create a patch signature
     * @param string $path
     * @return self
     */
    public static function patch(string $path)  :   self { return new self($path, "PATCH");     }
    
    /**
     * Shortcut to create a delete signature
     * @param string $path
     * @return self
     */
    public static function delete(string $path) :   self { return new self($path, "DELETE");    }
    
    /**
     * Shortcut to create a head signature
     * @param string $path
     * @return self
     */
    public static function head(string $path)   :   self { return new self($path, "HEAD");      }
    
    /**
     * Shortcut to create a connect signature
     * @param string $path
     * @return self
     */
    public static function connect(string $path):   self { return new self($path, "CONNECT");   }
    
    /**
     * Shortcut to create an options signature
     * @param string $path
     * @return self
     */
    public static function options(string $path):   self { return new self($path, "OPTIONS");   }
    
    /**
     * Shortcut to create a trace signature
     * @param string $path
     * @return self
     */
    public static function trace(string $path)  :   self { return new self($path, "TRACE");     }   
}

```
</details>


## Quick Start

### Installation

To get started, install the package via Composer:

```bash
composer require virgile/routing
```

### Route creation

Once your router is instantiated, you can register your routes.  
Virgile can support both static paths and dynamic parameters depending on how you implement the `extractParams()` method.

```php

use Virgile\Routing\Router;

$router = new Router();

// Define a simple route
$router->add(HttpSignature::get('/users'), [UserController::class, 'index']);

// Define a route with dynamic parameters
$router->add(HttpSignature::get('/users/{id}'), [UserController::class, 'show']);

```

### Groups and Middlewares

To keep your routing definitions clean, you can group routes under a common prefix and attach middlewares (like authentication or logging) that will automatically apply to all routes inside the group.

```php

$router->group(HttpSignature::get('/api/v1'), function(Router $router){
	$router->add(HttpSignature::get('/posts'), [PostController::class, 'index']);
}, [AuthMiddleware::class]);

```

### Controller Scope

If a group of routes shares the exact same controller, you can use the controller() scope helper. This allows you to omit the controller name in individual route definitions and simply reference the method name.

```php

$router->controller(UserController::class, function(Router $router){
    $router->add(HttpSignature::get('/users'), 'index'); // Maps to [UserController::class, 'index']
	$router->add(HttpSignature::get('/users/{id}'), 'show'); // Maps to [UserController::class, 'show']
});

```

### Matching a Route

When an incoming request hits your application, you generate its corresponding signature and pass it to the router's match() method. The router traverses the tree, aggregates hierarchical middlewares, and retrieves the matching route.

```php

use Virgile\Routing\Exceptions\RouteNotFoundException;

try {
    $signature = HttpSignature::get('/users/42');
	$route = $router->match($signature);
	
	// Extract dynamic parameters from expected Signature and actual request Signature
	$params = $route->getSignature()->extractParams($signature);
    $handler = $route->getHandler();
    
    // Dispatch your handler...
}catch(RouteNotFoundException $e){
    // Handle exception
}

```

### Node inspection

If you need to traverse the routing tree to inspect nodes, gather hierarchical middlewares, or check for an associated route without requiring a direct match, you can use the `findNode()` method.

```php
use Virgile\Routing\Exceptions\RouteNotFoundException;

try {
    $signature = HttpSignature::get('/api/v1/posts');    
    $node = $router->findNode($signature);    
    $route = $node->getRoute();    
    $middlewares = $node->getMiddlewares();
    
} catch (RouteNotFoundException $e) {
    // Handle exception
}
```

## Testing

You can run the test suite via PHPUnit, run static analysis with PHPStan or check coding standards with PHP-CS-Fixer

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## License

MIT
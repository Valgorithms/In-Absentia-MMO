<?php

declare(strict_types=1);

namespace BackendPhp\Api;

use BackendPhp\Support\Container;
use Discord\Helpers\Collection;
use Discord\Repository\AbstractRepositoryTrait;

/**
 * Abstract repository that extends a Collection so repositories behave like collections
 * (mirroring DiscordPHP's AbstractRepository pattern).
 */
abstract class AbstractRepository extends Collection implements RepositoryInterface
{
    use AbstractRepositoryTrait;

    /**
     * The collection discriminator.
     *
     * @var string Discriminator.
     */
    protected $discrim = 'id';

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Class type allowed into the collection.
     *
     * @var string
     */
    protected $class;

    /**
     * The HTTP http.
     *
     * @var mixed Optional HTTP/http or container
     */
    protected $http;

    /**
     * The parts factory.
     *
     * @var Factory Parts factory.
     */
    protected $factory;

    /**
     * Endpoints for interacting with the Discord servers.
     *
     * @var array Endpoints.
     */
    protected $endpoints = [];

    /**
     * Variables that are related to the repository.
     *
     * @var array Variables.
     */
    protected $vars = [];

    /**
     * @var CacheWrapper
     */
    protected $cache;

    public function __construct(Container $container, array $vars = [])
    {
        $this->http = $container->get('http');
        $this->factory = $container->get('factory');
        $this->vars = $vars;
    }

    /**
     * Default freshen implementation returning serialized collection.
     */
    public function freshen(array $params = []): array
    {
        // Ignore $params by default; concrete repositories may override behavior.
        return $this->jsonSerialize();
    }

    /**
     * Default fetch implementation using the collection's discrim.
     */
    public function fetch(string $id): array|null
    {
        $item = $this->get($this->discrim, $id);
        if ($item === null) {
            return null;
        }

        // If the item is an object with jsonSerialize, try to convert to array
        if (is_object($item) && method_exists($item, 'jsonSerialize')) {
            return $item->jsonSerialize();
        }

        return is_array($item) ? $item : (array) $item;
    }

    /**
     * Concrete repositories must implement resource mutations.
     */
    abstract public function save(array $data): array;

    abstract public function update(string $id, array $data): array;

    abstract public function delete(string $id): bool;

    protected function request(string $method, string $path, ?array $body = null): array
    {
        $req = ['method' => strtoupper($method), 'path' => $path];
        if ($body !== null) {
            $req['body'] = $body;
        }
        return $req;
    }

    protected function withQuery(string $path, array $params = []): string
    {
        if (empty($params)) {
            return $path;
        }

        return $path . '?' . http_build_query($params);
    }

    public function getClient()
    {
        return $this->http;
    }

    /**
     * Bind placeholder tokens in an endpoint template.
     * Example: bindPath('/items/:id', ['id' => 123]) => '/items/123'
     */
    protected function bindPath(string $template, array $params = []): string
    {
        // Prefer the project's Endpoint class if provided, then fall back to Discord's.
        if (class_exists(\BackendPhp\Api\Endpoint::class)) {
            $endpoint = new \BackendPhp\Api\Endpoint($template);
            if (! empty($params)) {
                $endpoint->bindAssoc($params);
            }

            return (string) $endpoint;
        }

        if (class_exists(\Discord\Http\Endpoint::class)) {
            $endpoint = new \Discord\Http\Endpoint($template);
            if (! empty($params)) {
                // Bind associative params where keys match :placeholders
                $endpoint->bindAssoc($params);
            }

            return (string) $endpoint;
        }

        // Fallback: simple placeholder replacement
        $path = $template;
        foreach ($params as $key => $value) {
            $path = str_replace(':' . $key, urlencode((string) $value), $path);
        }

        return $path;
    }
}

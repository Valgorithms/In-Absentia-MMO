<?php

declare(strict_types=1);

namespace BackendPhp\Api;

use Discord\Http\EndpointInterface;
use Discord\Http\EndpointTrait;

class Endpoint implements EndpointInterface
{
    use EndpointTrait;

    public const BASE = '/api/v1';

    // Auth
    public const AUTH_LOGIN = self::BASE . '/auth/login';
    public const AUTH_REGISTER = self::BASE . '/auth/register';
    public const AUTH_REFRESH = self::BASE . '/auth/refresh';

    // Account
    public const ACCOUNT = self::BASE . '/account';
    public const ACCOUNT_SKILLPOINTS = self::ACCOUNT . '/skillpoints';
    public const ACCOUNT_LIBRARY = self::ACCOUNT . '/knowledge-library';

    // Characters
    public const CHARACTERS = self::BASE . '/characters';
    // Single-character resource with placeholder
    public const CHARACTER = self::CHARACTERS . '/:id';
    public const CHARACTER_STATS = self::CHARACTER . '/stats';
    public const CHARACTER_EXPERTISE = self::CHARACTER . '/expertise';
    public const CHARACTER_KNOWLEDGE = self::CHARACTER . '/knowledge';
    public const CHARACTER_INVENTORY = self::CHARACTER . '/inventory';
    public const CHARACTER_CONTRACTS = self::CHARACTER . '/contracts';
    public const CHARACTER_WALLET = self::CHARACTER . '/wallet';
    public const CHARACTER_RESEARCH_JOURNAL = self::CHARACTER . '/research-journal';
    public const CHARACTER_RESEARCH_DISCOVERIES = self::CHARACTER . '/research-discoveries';

    // (Use placeholder constants and Discord\Http\Endpoint::bind for parameter binding.)

    // Contracts
    public const CONTRACTS = self::BASE . '/contracts';
    public const CONTRACT = self::CONTRACTS . '/:id';
    public const CONTRACT_CONTRIBUTIONS = self::CONTRACT . '/contributions';
    public const CONTRACT_CONTRIBUTE = self::CONTRACT . '/contribute';
    public const CONTRACT_WITHDRAW = self::CONTRACT . '/withdraw';
    public const CONTRACT_APPROVE = self::CONTRACT . '/approve/:charId';
    public const CONTRACT_INVITE = self::CONTRACT . '/invite/:charId';
    public const CONTRACT_TRIAL = self::CONTRACT . '/trial';
    public const CONTRACT_TRIALS = self::CONTRACT . '/trials';

    // (Use placeholder constants and Discord\Http\Endpoint::bind for parameter binding.)

    // Knowledge
    public const KNOWLEDGE = self::BASE . '/knowledge';
    public const KNOWLEDGE_ITEM = self::KNOWLEDGE . '/:id';
    public const KNOWLEDGE_RESEARCH_PATHS = self::KNOWLEDGE . '/:id/research-paths';

    // (Use placeholder constants and Discord\Http\Endpoint::bind for parameter binding.)

    // World
    public const WORLD_TIME = self::BASE . '/world/time';
    public const WORLD_ZONES = self::BASE . '/world/zones';
    public const WORLD_ZONE = self::WORLD_ZONES . '/:id';
    public const WORLD_ZONE_BUILDINGS = self::WORLD_ZONE . '/buildings';
    public const WORLD_ZONE_STOCKPILE = self::WORLD_ZONE . '/stockpile';
    public const WORLD_ZONE_MANA = self::WORLD_ZONE . '/mana';
    public const WORLD_ZONE_TREASURY = self::WORLD_ZONE . '/treasury';

    public const WORLD_EVENTS = self::BASE . '/world/events';
    public const WORLD_RAIDS_NEXT = self::BASE . '/world/raids/next';

    // Market
    public const MARKET_LISTINGS = self::BASE . '/market/listings';
    public const MARKET_LIST = self::BASE . '/market/listings';
    public const MARKET_BUY = self::BASE . '/market/buy';
    public const MARKET_HISTORY = self::BASE . '/market/history';

    // Transfers / Economy
    public const TRANSFERS = self::BASE . '/transfers';
    public const ECONOMY_STATS = self::BASE . '/economy/stats';

    // Governance
    public const GOVERNANCE_OFFICES = self::BASE . '/governance/offices';
    public const GOVERNANCE_ELECTIONS = self::BASE . '/governance/elections';
    public const GOVERNANCE_POLICIES = self::BASE . '/governance/policies';

    public const GOVERNANCE_ELECTION_VOTE = self::GOVERNANCE_ELECTIONS . '/:id/vote';
    public const GOVERNANCE_PROPOSE_POLICY = self::GOVERNANCE_POLICIES;

    // WebSocket
    public const WS = self::BASE . '/ws';

    /**
     * Regex to identify parameters in endpoints.
     *
     * @var string
     */
    public const REGEX = '/:([^\/]*)/';

    /**
     * A list of parameters considered 'major'.
     * 
     * This is not currently used and should be updated at a later date to reflect our own API's major parameters, if any. It is included here for completeness and future-proofing.
     *
     * @see https://discord.com/developers/docs/topics/rate-limits
     * @var string[]
     */
    public const MAJOR_PARAMETERS = ['channel_id', 'guild_id', 'webhook_id', 'thread_id'];

    /**
     * The string version of the endpoint, including all parameters.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Array of placeholders to be replaced in the endpoint.
     *
     * @var string[]
     */
    protected $vars = [];

    /**
     * Array of arguments to substitute into the endpoint.
     *
     * @var string[]
     */
    protected $args = [];

    /**
     * Array of query data to be appended
     * to the end of the endpoint with `http_build_query`.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Creates an endpoint class.
     *
     * @param string $endpoint
     */
    public function __construct(string $endpoint)
    {
        $this->endpoint = $endpoint;

        if (preg_match_all(self::REGEX, $endpoint, $vars)) {
            $this->vars = $vars[1] ?? [];
        }
    }
}

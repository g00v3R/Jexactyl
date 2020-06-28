<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Carbon\Carbon;
use ReflectionClass;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Location;
use Illuminate\Support\Collection;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Transformers\Api\Client\BaseClientTransformer;

abstract class ClientApiIntegrationTestCase extends IntegrationTestCase
{
    /**
     * Cleanup after running tests.
     */
    protected function tearDown(): void
    {
        Server::query()->forceDelete();
        Node::query()->forceDelete();
        Location::query()->forceDelete();
        User::query()->forceDelete();

        parent::tearDown();
    }

    /**
     * Setup tests and ensure all of the times are always the same.
     */
    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::now());
        CarbonImmutable::setTestNow(Carbon::now());
    }

    /**
     * Generates a user and a server for that user. If an array of permissions is passed it
     * is assumed that the user is actually a subuser of the server.
     *
     * @param string[] $permissions
     * @return array
     */
    protected function generateTestAccount(array $permissions = []): array
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = factory(User::class)->create();

        if (empty($permissions)) {
            return [$user, $this->createServerModel(['user_id' => $user->id])];
        }

        $server = $this->createServerModel();

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => $permissions,
        ]);

        return [$user, $server];
    }

    /**
     * Asserts that the data passed through matches the output of the data from the transformer. This
     * will remove the "relationships" key when performing the comparison.
     *
     * @param array $data
     * @param \Pterodactyl\Models\Model|\Illuminate\Database\Eloquent\Model $model
     */
    protected function assertJsonTransformedWith(array $data, $model)
    {
        $reflect = new ReflectionClass($model);
        $transformer = sprintf('\\Pterodactyl\\Transformers\\Api\\Client\\%sTransformer', $reflect->getShortName());

        $transformer = new $transformer;
        $this->assertInstanceOf(BaseClientTransformer::class, $transformer);

        $this->assertSame(
            $transformer->transform($model),
            Collection::make($data)->except(['relationships'])->toArray()
        );
    }
}
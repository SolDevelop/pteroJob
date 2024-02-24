<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\WebsocketController;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Services\Servers\GetUserPermissionsService;
use WebSocket\Client;

class WatchDog extends Command
{
    public function __construct(
        private NodeJWTService $jwtService,
        private GetUserPermissionsService $permissionsService
    ) {
        parent::__construct();
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function getWebSocketData($serverUUIDShort)
    {
        $server = Server::where("uuidShort", $serverUUIDShort)->first();
        $user = User::inRandomOrder()->get();
        $user = User::find($user[0]['id']);
        $node = $server->node;
        $permissions = $this->permissionsService->handle($server, $user);
        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(10))
            ->setUser($user)
            ->setClaims([
                'server_uuid' => $server->uuid,
                'permissions' => $permissions,
            ])
            ->handle($node, $user->id . $server->uuid);

        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $node->getConnectionAddress());

        return ['data' => ['token' => $token->toString(), 'socket' => $socket . sprintf('/api/servers/%s/ws', $server->uuid),],];
    }
    public function checkIfUp($server)
    {

        $data = $this->getWebSocketData($server['uuidShort'])['data'];
        
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $data = [];
        $message = $client->receive();
        $client->text('{
	"event": "send stats",
	"args": [
		"' . $token . '"]
}');
        $message = $client->receive();
        $client->close();
        if (json_decode($message, true)['args'][0] == 'running' || json_decode($message, true)['args'][0] == 'starting') {
            return true;
        } else {
            return false;
        }
    }
    public function WakeUp($uuidshort)
    {
        $data = $this->getWebSocketData($uuidshort)['data'];
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $data = [];
        $message = $client->receive();
        $client->text('{
	"event": "set state",
	"args": ["start"]
}');
        $message = $client->receive();
        $client->close();
        return true;
    }
    public function Restart($uuidshort)
    {
        $data = $this->getWebSocketData($uuidshort)['data'];
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $data = [];
        $message = $client->receive();
        $client->text('{
	"event": "set state",
	"args": ["restart"]
}');
        $message = $client->receive();
        $client->close();
        return true;
    }
    public function stopServer($uuidshort)
    {

        $data = $this->getWebSocketData($uuidshort)['data'];
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $data = [];
        $message = $client->receive();
        $client->text('{
	"event": "set state",
	"args": ["stop"]
}');
        $message = $client->receive();
        $client->close();
        return true;
    }
    public function killServer($uuidshort)
    {

        $data = $this->getWebSocketData($uuidshort)['data'];
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $data = [];
        $message = $client->receive();
        $client->text('{
	"event": "set state",
	"args": ["kill"]
}');
        $message = $client->receive();
        $client->close();
        return true;
    }
    public function getPlayers($uuidshort)
    {



        $data = $this->getWebSocketData($uuidshort)['data'];
        $token = $data['token'];
        $socket = $data['socket'];

        $config = [
            'headers' => ['Origin' => 'http://localhost',],
        ];
        $client = new Client($socket, $config);
        $client->text('{
	"event": "auth",
	"args": [
		"' . $token . '"]
}');
        $message = $client->receive();
        $data = [];
        $client->text('{
                    "event": "send command",
                    "args": [
                        "/list"]
                }');
        $players = 0;
        while (true) {
            try {
                $message = $client->receive();
                if (json_decode($message, true)['event'] == 'console output') {
                    $string = json_decode($message, true)['args'][0];
                    $players = preg_match('/(\d+)\/\d+/', $string, $matches) ? $matches[1] : null;
                    ;
                    break;
                }
            } catch (\WebSocket\ConnectionException $e) {
                continue;
            }
        }
        $client->close();
        return $players;

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        $allservers = Server::all();
        foreach ($allservers as $server) {
            if ($server->free){
                if ($this->checkIfUp($server)) {
                    if ($this->getPlayers($server->uuidShort) == 0) {
                        $this->killServer($server->uuidShort);
                        $this->info('Server Number: '.$server->id. ' has been shut off');
                }
            }
        }
        $this->info('Inactive Servers has been shut off');
        return Command::SUCCESS;
    }
}
}
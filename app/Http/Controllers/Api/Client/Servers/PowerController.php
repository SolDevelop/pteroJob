<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SendPowerRequest;
use WebSocket\Client;
use Illuminate\Support\Facades\Http;


class PowerController extends ClientApiController
{
    /**
     * PowerController constructor.
     */
    public function __construct(private DaemonPowerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Send a power action to a server.
     */
    public function checkIfUp($server)
    {

        $data = Http::withToken('ptla_UqSa9KFPK9V5SV5sh1nodEzEY5Ozsw3QCfbE42xvLKN')->get('http://localhost/api/client/servers/' . $server['uuidShort'] . '/websocket')['data'];
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
        if (json_decode($message, true)['args'][0] == 'running') {
            return true;
        } else {
            return false;
        }
    }
    public function killServer($uuidshort)
    {

        $data = Http::withToken('ptla_UqSa9KFPK9V5SV5sh1nodEzEY5Ozsw3QCfbE42xvLKN')->get('http://localhost/api/client/servers/' . $uuidshort . '/websocket')['data'];
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
        return true;
    }
    public function index(SendPowerRequest $request, Server $server): Response
    {
        $node = $server->node;
        $requesteduuid = $server->uuid;
        $requestedmemory = $server->memory;
        $fullservers = $node->servers;
        $arr = [
            'Node' => $node->uuid,
            'Max-Memory' => $node->memory,
            'Servers' => []
        ];
        foreach ($fullservers as $server) {
            if ($server->free == "Free") {
                if ($server->uuid == $requesteduuid) {
                    continue;
                }
                $arr['Servers'][] = ["memory" => $server->memory, "status" => $this->checkIfUp($server), "Uuid" => $server->uuid, "UuidShort"=> $server->uuidShort];
            }

        }
        $max = $arr['Max-Memory'];
        $current = 0;
        foreach ($arr['Servers'] as $server) {
            if ($server['status'] == true) {
                $current += $server['memory'];
            }
        }
        $fuller = $current + $requestedmemory;
        $checker = $max - $fuller;
        if ($checker <= 0) {
            $time_start = microtime(true);
            $endtime = $time_start + 60;
            while ($endtime > microtime(true)) {
                $e = "";
                
            }
            $serversArray = $arr["Servers"];
                $randomServer = $serversArray[array_rand($serversArray)];
                if (killServer($randomServer['UuidShort'])){
                    $this->repository->setServer($server)->send(
                        $request->input('signal')
                    );
                }
        } else {
            $this->repository->setServer($server)->send(
                $request->input('signal')
            );

            Activity::event(strtolower("server:power.{$request->input('signal')}"))->log();
        }

    }
    
    public function checker(Request $request, Server $server)
    {

        $node = $server->node;
        $requesteduuid = $server->uuid;
        $requestedmemory = $server->memory;
        $fullservers = $node->servers;
        $arr = [
            'Node' => $node->uuid,
            'Max-Memory' => $node->memory,
            'Servers' => []
        ];
        foreach ($fullservers as $server) {
            if ($server->free == "Free") {
                if ($server->uuid == $requesteduuid) {
                    continue;
                }
                $arr['Servers'][] = ["memory" => $server->memory, "status" => $this->checkIfUp($server), "Uuid" => $server->uuid];
            }

        }
        $max = $arr['Max-Memory'];
        $current = 0;
        foreach ($arr['Servers'] as $server) {
            if ($server['status'] == true) {
                $current += $server['memory'];
            }
        }
        $fuller = $current + $requestedmemory;
        $checker = $max - $fuller;
        if ($checker <= 0) {
            return response(['status' => 'getready1m'], 200);
        } else {
            return response(['status' => 'requeststart'], 200);
        }

    }
}

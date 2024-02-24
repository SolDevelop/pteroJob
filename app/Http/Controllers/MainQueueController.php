<?php

namespace Pterodactyl\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\WebsocketController;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Services\Servers\GetUserPermissionsService;
use WebSocket\Client;

class MainQueueController extends ApplicationApiController
{
    public function __construct(
        private NodeJWTService $jwtService,
        private GetUserPermissionsService $permissionsService
    ) {
        parent::__construct();
    }
    public function getWebSocketData($serverUUIDShort)
    {
        $server = Server::where("uuidShort", $serverUUIDShort)->first();
        $user = auth()->user();
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
    public function index(Request $request, Server $server)
    {
        if ($request->all()['pos']){
            $uuidshort = $server->uuidShort;
        $uuid = $server->uuid;
        $ticket = Ticket::where('uuidShort', $uuidshort)->first();
        if ($ticket){
        return response(["pos"=> $ticket->turn], 200);
        }else{

        return response(["pos"=> 0], 200);
        }
        }else{
            if ($request->all()['input']) {
                $uuidshort = $server->uuidShort;
            $uuid = $server->uuid;
            $tickets = Ticket::all();
            $tickets = $tickets->sortByDesc('turn');
            if ($tickets){
                foreach ($tickets as $ticket){
                    $serv = Server::where('uuidShort', $ticket->uuidShort)->first();
                    $node = $serv->node;
                    $requesteduuid = $serv->uuid;
                    $requestedmemory = $serv->memory;
                    $fullservers = $node->servers;
                    $arr = [
                        'Node' => $node->uuid,
                        'Max-Memory' => config('pterodactyl.freeram'),
                        'Servers' => []
                    ];
                    foreach ($fullservers as $sep) {
                        if ($sep->free == "Free") {
                            if ($sep->uuid == $requesteduuid) {
                                continue;
                            }
                            $arr['Servers'][] = ["memory" => $sep->memory, "status" => $this->checkIfUp($sep), "Uuid" => $sep->uuid, "UuidShort" => $sep->uuidShort];
                        }
    
                    }
                    $max = $arr['Max-Memory'];
                    $current = 0;
                    foreach ($arr['Servers'] as $sep) {
                        if ($server['status'] == true) {
                            $current += $sep['memory'];
                        }
                    }
                    $fuller = $current + $requestedmemory;
                    $checker = $max - $fuller;
                    if (($max - $fuller) > 0){
                        $this->WakeUp($ticket->uuidShort);
                    $checker = $this->checkIfUp(Server::where('uuidShort', $ticket->uuidShort)->first());
                    if ($checker){
                        $ticket->delete();
                    }
                    }
                    
                }
            }
                $data = $request->all()['input'];
                if ($data['input'] == 'start') {
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
                            $arr['Servers'][] = ["memory" => $server->memory, "status" => $this->checkIfUp($server), "Uuid" => $server->uuid, "UuidShort" => $server->uuidShort];
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
                    //return response($arr);
                    $time_start = microtime(true);
    $endtime = $time_start + 20;
    $counter = 0;
                    
                    $servers = $arr['Servers'];
    
    // Shuffle the array
    shuffle($servers);
    
    // Iterate through the shuffled array
    foreach ($servers as $server) {
        if (($max - $fuller) >= 0){
            break;
        }
                        if ($this->checkIfUp(Server::where('uuidShort', $server['UuidShort'])->first()) == true) {
                            
                            if ($this->getPlayers($server['UuidShort']) == 0) {
                             
                                $returner = $this->killServer($server['UuidShort']);
                                
                                if ($returner) {
                                    $fuller -= $server['memory'];
                                }
                                
                            }else{
                               continue;
                            }
                        }
                        $counter += 1;
    }
                    /*while (($max - $fuller) < 0 || $endtime > microtime(true) || count($arr['Servers']) !== $counter) {
                        $serversArray = $arr["Servers"];
                        $randomServer = $serversArray[array_rand($serversArray)];
                        if ($this->checkIfUp(Server::where('uuidShort', $randomServer['UuidShort'])->first()) == true) {
                            
                            if ($this->getPlayers($randomServer['UuidShort']) == 0) {
                             
                                $returner = $this->killServer($randomServer['UuidShort']);
                                
                                if ($returner) {
                                    $fuller -= $randomServer['memory'];
                                }
                                
                            }else{
                                return response($this->getPlayers($randomServer['UuidShort']));
                            }
                        }
                        $counter += 1;
                    }*/
                    if (($max - $fuller) >= 0){
                        $ticket = Ticket::where('uuidShort', $uuidshort)->first();
                        $this->WakeUp($uuidshort);
                        if ($ticket){
                            $checker = $this->checkIfUp(Server::where('uuidShort', $ticket->uuidShort)->first());
                    if ($checker){
                        $ticket->delete();
                    }else{
                        return response(['status' => ['code' => 'noslots', 'message' => 'There is no place for your server please try again in a minute']], 401);
                    }
                        }
                    
    
                                    }else{
                                        $ticket = Ticket::where('uuidShort', $uuidshort)->first();
                                        if ($ticket){
                                            return response(['status' => ['code' => 'noslots', 'message' => 'There is no place for your server please try again in a minute']], 401);
                                   
                                        }else{
                                            $ticket = new Ticket();
                                        $ticket->uuid = $uuid;
                                        $ticket->uuidShort= $uuidshort;
                                        $ticket->node = $node;
                                        $ticket->turn = Ticket::count() + 1;
                                        $ticket->save();
                                        return response(['status' => ['code' => 'noslots', 'message' => 'There is no place for your server please try again in a minute, Your Position has been saved']], 401);
                                   
                                        }
                                        
                                         }
                } else if ($data['input'] == 'restart') {
                    if ($this->checkIfUp(Server::where('uuidShort', $uuidshort)->first())) {
    
                        $this->Restart($uuidshort);
                    } else {
                        return response(['status'=>['code'=> 'usestart', 'message'=> 'Cannot do a restart without having the server up']], 401);
                    }
                } else if ($data['input'] == 'stop') {
                    $this->stopServer($uuidshort);
                } else if ($data['input'] == 'kill') {
                    $this->killServer($uuidshort);
                }
                return response(['status' => ['code' => 'sucess', 'message' => 'Done']], 200);
            } else {
                return response(['status' => ['code' => 'missing', 'message' => 'Some of the Request\'s Data is missing, Try Again']], 400);
            }
        }
        
    }
    
}

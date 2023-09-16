<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SendPowerRequest;

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
    public function index(SendPowerRequest $request, Server $server): Response
    {   
        // from here to 
        if ($server->free == 1){
            $this->repository->setServer($server)->send(
                $request->input('signal')
            );
    
            Activity::event(strtolower("server:power.{$request->input('signal')}"))->log();
    
        }else{
            $this->repository->setServer($server)->send(
                $request->input('signal')
            );
    
            Activity::event(strtolower("server:power.{$request->input('signal')}"))->log();
    
        }
        // here
        
        return $this->returnNoContent();
    }
}

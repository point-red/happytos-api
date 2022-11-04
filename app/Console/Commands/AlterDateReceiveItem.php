<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\Inventory\TransferItem\ReceiveItem;
use App\Model\Project\Project;
use Illuminate\Support\Facades\DB;

class AlterDateReceiveItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:alter-date-receive-item';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temporary function to normalize the date of receive items';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    { 
        $projects = Project::where('is_generated', true)->get();
        foreach ($projects as $project) {
            $this->line('Alter '.$project->code);
            config()->set('database.connections.tenant.database', env('DB_DATABASE').'_'.strtolower($project->code));
            
            DB::connection('tenant')->reconnect();
            DB::connection('tenant')->beginTransaction();

            $receiveItems = ReceiveItem::all();
            foreach ($receiveItems as $receiveItem){
                $transferItem = $receiveItem->transfer_item;
                if ($receiveItem->form->date < $transferItem->form->date){
                    $receiveItem->form->update(['date' => $transferItem->form->date]);
                }
            }

            DB::connection('tenant')->commit();

            $this->info("Successfully alter date receive items");
        }
    }
}

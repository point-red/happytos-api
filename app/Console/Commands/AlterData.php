<?php

namespace App\Console\Commands;

use App\Model\Auth\Permission;
use App\Model\Form;
use App\Model\Inventory\Inventory;
use App\Model\Master\Warehouse;
use App\Model\Project\Project;
use App\Model\Inventory\StockCorrection\StockCorrection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class AlterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:alter-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temporary';

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
            $this->line('Clone '.$project->code);
            // Artisan::call('tenant:database:backup-clone', ['project_code' => strtolower($project->code)]);

            $this->line('Alter '.$project->code);
            config()->set('database.connections.tenant.database', env('DB_DATABASE').'_'.strtolower($project->code));
            
            DB::connection('tenant')->reconnect();
            DB::connection('tenant')->beginTransaction();

            $stockCorrections = StockCorrection::all();
            foreach($stockCorrections as $stockCorrection) {
                $count1 = Inventory::where('form_id', $stockCorrection->form->id)->count();
                $count2 = $stockCorrection->items->count();
                if ($count1 !== $count2) {
                    $this->line('form id = ' . $stockCorrection->form->id . ' ' . $stockCorrection->form->date);
                }
            }

            DB::connection('tenant')->commit();
        }
    }
}

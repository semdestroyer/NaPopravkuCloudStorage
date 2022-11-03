<?php

namespace App\Console\Commands;

use App\Models\FileCleaning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloud:cleanFiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $files = FileCleaning::all();
        foreach ($files as $file)
        {
            if($file->createdAt <= $file->deleteAt)
            {
                Storage::disk('local')->delete($file->path);
                $file->delete();
            }
        }
        return Command::SUCCESS;
    }
}

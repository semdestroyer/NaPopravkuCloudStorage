<?php

namespace App\Console\Commands;

use App\Models\FileCleaning;
use App\Models\FileUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloud:clean';

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
            if(strtotime('now') >= strtotime($file->deleteAt))
            {
                $fileUrl = FileUrl::where('path', $file->path)->first();
                Storage::disk('local')->delete($file->path);
                $file->delete();
                if(!empty($fileUrl)) {
                    $fileUrl->delete();
                }
            }
        }
        return Command::SUCCESS;
    }
}

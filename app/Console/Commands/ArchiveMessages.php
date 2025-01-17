<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Storage;
use Symfony\Component\Process\Process;

class ArchiveMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:archive-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archives messages from last 30 days to .sql.gz file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = DB::connection()->getConfig();

        // TODO check if connection is mysql or mariadb

        $from_date = now()->subDays(30);

        $filename = $from_date->format('Y_m_d-') . now()->format('Y_m_d_') . time() . '.sql';

        $table = Message::newModelInstance()->getTable();

        $query = Message::where('created_at', '>', $from_date);

        // TODO check if mysqldump command is available

        if(($count = $query->count()) === 0) {
            $this->warn('No messages to archive.');

            $this->cleanup();

            return 0;
        }

        $process = new Process([
            'mysqldump',
            '-h' . $config['host'],
            '-p' . $config['port'],
            '-u' . $config['username'],
            '-p' . $config['password'],
            '-r' . $path = Storage::path("backups/$filename"),
            '--column-statistics=0',
            '--skip-add-drop-table',
            '--no-create-info',
            '--where=created_at > \'' . now()->subDays(30) . "'",
            $config['database'],
            $table,
        ]);

        $process->mustRun();

        // TODO verify that file exists and its content is correct

        // TODO check if gzip command is available

        $process = new Process(['gzip', $path]);

        $process->mustRun();

        $query->delete();

        $this->output->success("Successfully archived $count messages.");

        $this->cleanup();

        return 0;
    }

    /**
     * Delete archives older than 6 months
     */
    private function cleanup(): void
    {
        $files = Storage::files('backups');

        $count = 0;
        $size = 0;

        foreach ($files as $file) {
            $date = Str::of($file)->after('-')->beforeLast('_');

            if (Carbon::createFromFormat('Y_m_d', $date)?->isBefore(now()->subMonths(6))) {
                $size += round(Storage::size($file) / 1024);
                $count++;

                Storage::delete($file);
            }
        }

        if($count === 0) {
            return;
        }

        $this->output->info("Deleted $count old archives. Freed up total $size KB.");
    }
}

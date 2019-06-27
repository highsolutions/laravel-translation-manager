<?php 

namespace Imtigger\TranslationManager\Console;

use Imtigger\TranslationManager\Manager;
use Illuminate\Console\Command;

class GenerateCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'translations:generate {--export : Export translation immediately} {--group=backend : Translation group}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate translations strings from key';

    /** @var \Imtigger\TranslationManager\Manager  */
    protected $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $count = $this->manager->generateTranslations($this->option('group'));
        $this->info("Done generating translations for {$count} records.");
    }

}

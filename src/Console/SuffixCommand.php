<?php 

namespace Imtigger\TranslationManager\Console;

use Imtigger\TranslationManager\Manager;
use Illuminate\Console\Command;

class SuffixCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'translations:suffix {basic : basic language name} {new : new language}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add locale suffix to all translations that are the same as basic one.';

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
        $count = $this->manager->suffixTranslations($this->argument('basic'), $this->argument('new'));
        $this->info("Done suffixing translations for {$count} records.");
    }

}

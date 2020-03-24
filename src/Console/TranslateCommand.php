<?php 

namespace Imtigger\TranslationManager\Console;

use Imtigger\TranslationManager\Manager;
use Illuminate\Console\Command;

class TranslateCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'translations:translate 
    {--from-locale=en : Translation locale}
    {--to-locale=zh-Hant : Translation locale}
    {--group=backend : Translation group}';

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
        $count = $this->manager->translateTranslations($this->option('from-locale'), $this->option('to-locale'), $this->option('group'));
        $this->info("Done translating translations for {$count} records.");
    }

}

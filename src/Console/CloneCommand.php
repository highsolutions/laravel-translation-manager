<?php 

namespace Imtigger\TranslationManager\Console;

use Imtigger\TranslationManager\Manager;
use Illuminate\Console\Command;

class CloneCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'translations:clone {from : basic language name} {to : new language}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clone translations from one language to another';

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
        $this->manager->cloneTranslations($this->argument('from'), $this->argument('to'));
        $this->info("Done cloning translations");
    }

}

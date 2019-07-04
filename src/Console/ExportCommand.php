<?php 

namespace Imtigger\TranslationManager\Console;

use Imtigger\TranslationManager\Manager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class ExportCommand extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'translations:export {group : The group to export (`*` for all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translations to PHP files';

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
        $group = $this->argument('group');

        $this->manager->exportTranslations($group);

        $this->info("Done writing language files for " . (($group == '*') ? 'ALL groups' : $group . " group") );

    }

}

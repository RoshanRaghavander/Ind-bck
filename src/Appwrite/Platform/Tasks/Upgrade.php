<?php

namespace Appwrite\Platform\Tasks;

use Utopia\CLI\Console;
use Utopia\Validator\Boolean;
use Utopia\Validator\Text;

class Upgrade extends Install
{
    public static function getName(): string
    {
        return 'upgrade';
    }

    public function __construct()
    {
        $this
            ->desc('Upgrade Indobase')
            ->param('http-port', '', new Text(4), 'Server HTTP port', true)
            ->param('https-port', '', new Text(4), 'Server HTTPS port', true)
            ->param('organization', 'indobase', new Text(0), 'Docker Registry organization', true)
            ->param('image', 'indobase', new Text(0), 'Main Indobase docker image', true)
            ->param('interactive', 'Y', new Text(1), 'Run an interactive session', true)
            ->param('no-start', false, new Boolean(true), 'Run an interactive session', true)
            ->callback($this->action(...));
    }

    public function action(string $httpPort, string $httpsPort, string $organization, string $image, string $interactive, bool $noStart): void
    {
        // Check for previous installation
        $data = @file_get_contents($this->path . '/docker-compose.yml');
        if (empty($data)) {
            Console::error('Indobase installation not found.');
            Console::log('The command was not run in the parent folder of your indobase installation.');
            Console::log('Please navigate to the parent directory of the Indobase installation and try again.');
            Console::log('  parent_directory <= you run the command in this directory');
            Console::log('  └── indobase');
            Console::log('      └── docker-compose.yml');
            Console::exit(1);
        }
        parent::action($httpPort, $httpsPort, $organization, $image, $interactive, $noStart);
    }
}

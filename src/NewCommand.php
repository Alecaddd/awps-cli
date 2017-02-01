<?php

namespace Awps;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use GuzzleHttp\ClientInterface;
use ZipArchive;

class NewCommand extends Command
{
    private $client;
    private $textDomain;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    public function configure()
    {
        $this->setName('new')
          ->setDescription('Create a new AWPS Theme installation')
          ->addArgument('name', InputArgument::REQUIRED, 'Insert the folder name')
          ->addOption('textdomain', 'td', InputOption::VALUE_OPTIONAL, 'Specify the text domain of your Theme', 'your-theme');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = getcwd().'/'.$input->getArgument('name');

        $output->writeln('<info>Summoning application..</info>');

        $this->assertApplicationDoesNotExists($directory, $output);

        $this->assertLocationInsideWordPress($directory, $output);

        $output->writeln('<info>Downloading Package..</info>');

        $this->download($ZipFile = $this->makeFileName())
          ->extract($ZipFile, $directory)
          ->cleanUp($ZipFile);

        $this->askTextDomain($input, $output);

        // rename everything (namespace, text-domain)

        // transfer config file to base root

        // duplicate .env file

        $output->writeln('<comment>Application ready, Happy Coding!</comment>');
    }

    private function assertApplicationDoesNotExists($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            $output->writeln('<error>Folder name already exists!</error>');
            exit(1);
        }
    }

    private function assertLocationInsideWordPress($directory, OutputInterface $output)
    {
        if (!strpos(dirname($directory), 'wp-content')) {
            $output->writeln('<error>This is not a WordPress theme directory!</error>');
            exit(1);
        }
    }

    private function askTextDomain(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $question = new Question('What is the text-domain of your theme?', 'your-theme');

        $this->textDomain = $helper->ask($input, $output, $question);

        return $this;
    }

    private function makeFileName()
    {
        return getcwd().'/awps_'.md5(time().uniqid()).'.zip';
    }

    private function download($zipFile)
    {
        $response = $this->client->get('https://github.com/Alecaddd/awps/archive/master.zip')->getBody();

        file_put_contents($zipFile, $response);

        return $this;
    }

    private function extract($ZipFile, $directory)
    {
        $archive = new ZipArchive();

        $archive->open($ZipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    private function cleanUp($ZipFile)
    {
        @chmod($ZipFile, 0777);
        @unlink($ZipFile);

        return $this;
    }
}

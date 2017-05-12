<?php

namespace Awps;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\ClientInterface;
use ZipArchive;

class NewCommand extends Command
{
	private $client;

	public function __construct(ClientInterface $client)
	{
		$this->client = $client;

		parent::__construct();
	}

	public function configure()
	{
		$this->setName('new')
		->setDescription('Create a new AWPS Theme installation')
		->addArgument('name', InputArgument::REQUIRED, 'Insert the folder name');
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$directory = getcwd().'/'.$input->getArgument('name');

		$output->writeln('<info>Summoning application..</info>');

		$this->assertApplicationDoesNotExists($directory, $output);

		$this->assertLocationInsideWordPress($directory, $output);

		$output->writeln('<info>Downloading Package..</info>');

		$this->download($ZipFile = $this->makeFileName())
		->extract($ZipFile, $directory, $output)
		->cleanUp($ZipFile);

		$output->writeln('<info>Updating config files..</info>');

		// transfer config file to base root
		$this->moveConfig($directory, $output);

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

	private function extract($ZipFile, $directory, OutputInterface $output)
	{
		$archive = new ZipArchive();

		$archive->open($ZipFile);

		$archive->extractTo($directory);

		$archive->close();

		if (!empty(shell_exec("mv ".$directory."/awps-master/* ".$directory."/awps-master/.[!.]*  ".$directory))){
			$output->writeln('<comment>Unable to move files from master folder!</comment>');

			return $this;
		}

		rmdir($directory."/awps-master");

		return $this;
	}

	private function cleanUp($ZipFile)
	{
		@chmod($ZipFile, 0777);
		@unlink($ZipFile);

		return $this;
	}

	private function moveConfig($directory, OutputInterface $output)
	{
		if (!empty(shell_exec("mv ".$directory."/wp-config.sample.php ../../"))) {
			$output->writeln('<comment>Unable to move the wp-config.sample.php file, be sure to move it in the base directory!</comment>');
		}

		if (!empty(shell_exec("mv ".$directory."/.env.example ../../.env"))) {
			$output->writeln('<comment>Unable to move the .env.example file, be sure to move it in the base directory and rename it as .env!</comment>');
		}

		return $this;
	}

}

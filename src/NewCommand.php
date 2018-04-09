<?php
/**
 * Generate a new installation of AWPS
 *
 * @category CLI
 * @package  Awps-cli
 * @author   Alessandro Castellani <me@alecaddd.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @link     http://alecaddd.com
 */

namespace Awps;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Generate a new installation of AWPS
 *
 * @category CLI
 * @package  Awps-cli
 * @author   Alessandro Castellani <me@alecaddd.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @link     http://alecaddd.com
 */
class NewCommand extends Command
{
	private $client;

	public function __construct(ClientInterface $client)
	{
		$this->client = $client;

		parent::__construct();
	}

	/**
	 * Configure the CLI command
	 *
	 * @return void
	 */
	public function configure()
	{
		$this
			->setName('new')
			->setDescription('Create a new AWPS Theme installation')
			->addArgument('name', InputArgument::REQUIRED, 'Insert the folder name')
			->addOption('dev', null, InputOption::VALUE_NONE, 'Download the latest "development" release')
			->addOption('force', null, InputOption::VALUE_NONE, 'Forces the install even if the directory already exists');
	}

	/**
	 * Execute the creation command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		if (! class_exists('ZipArchive')) {
			throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
		}

		$folderName = $input->getArgument('name');
		$directory = getcwd().'/'.$folderName;

		$output->writeln('<info>Summoning application..</info>');

		if (! $input->getOption('force')) {
			$this->assertApplicationDoesNotExists($directory, $output);
		}

		$this->assertLocationInsideWordPress($directory, $output);

		$helper = $this->getHelper("question");

		$question = new Question("Name of your theme? <info>(Awps)</info> ", "Awps");
		$themeName = $helper->ask($input, $output, $question);

		$question = new Question("PHP Namespace of your theme? <info>(awps)</info> ", "awps");
		$namespace = $helper->ask($input, $output, $question);

		$question = new Question("Description? <info>(Alecaddd WordPress Starter theme)</info> ", "Alecaddd WordPress Starter theme");
		$description = $helper->ask($input, $output, $question);

		$output->writeln('<info>Downloading Package..</info>');

		$this->download($ZipFile = $this->makeFileName())
				->extract($ZipFile, $directory, $output)
				->renameNamespaces($directory, $output, $folderName, $namespace)
				->cleanUp($ZipFile);

		$output->writeln('<info>Updating config files..</info>');

		// transfer config file to base root
		$this->moveConfig($directory, $output);

		$output->writeln('<comment>Application ready, Happy Coding!</comment>');
	}

	/**
	 * Assert if the folder doesn't already exists
	 *
	 * @param string $directory
	 * @param OutputInterface $output
	 * @return void
	 */
	private function assertApplicationDoesNotExists($directory, OutputInterface $output)
	{
		if (is_dir($directory)) {
			$output->writeln('<error>Folder name already exists!</error>');
			exit(1);
		}
	}

	/**
	 * Assert the cli was triggerd inside a WordPress directory
	 *
	 * @param string $directory
	 * @param OutputInterface $output
	 * @return void
	 */
	private function assertLocationInsideWordPress($directory, OutputInterface $output)
	{
		if (! strpos(dirname($directory), 'wp-content')) {
			$output->writeln('<error>This is not a WordPress theme directory!</error>');
			exit(1);
		}
	}

	/**
	 * Generate a temporary file name for the downloaded zip file
	 *
	 * @return void
	 */
	private function makeFileName()
	{
		return getcwd().'/awps_'.md5(time().uniqid()).'.zip';
	}

	/**
	 * Download the lates AWPS release
	 *
	 * @param string $zipFile
	 * @return void
	 */
	private function download($zipFile)
	{
		$response = $this->client->get('https://github.com/Alecaddd/awps/archive/master.zip')->getBody();

		file_put_contents($zipFile, $response);

		return $this;
	}

	/**
	 * Extract the downlaod zip
	 *
	 * @param string $ZipFile
	 * @param string $directory
	 * @param OutputInterface $output
	 * @return void
	 */
	private function extract($ZipFile, $directory, OutputInterface $output)
	{
		$archive = new ZipArchive();

		$archive->open($ZipFile);

		$archive->extractTo($directory);

		$archive->close();

		if (! empty(shell_exec("mv ".$directory."/awps-master/* ".$directory."/awps-master/.[!.]*  ".$directory))) {
			$output->writeln('<comment>Unable to move files from master folder!</comment>');

			return $this;
		}

		rmdir($directory."/awps-master");

		return $this;
	}

	/**
	 * Rename the application PHP namespace
	 *
	 * @param string $directory
	 * @param OutputInterface $output
	 * @param string $folderName
	 * @param string $namespace
	 * @return void
	 */
	private function renameNamespaces($directory, OutputInterface $output, $folderName = null, $namespace = null)
	{
		if (is_null($folderName) && is_null($namespace)) {
			return $this;
		}

		$file_info = array();

		$this->recursiveScanFiles($directory, $file_info);

		foreach ($file_info as $file) {
			$str = file_get_contents($file);

			if (! is_null($namespace)) {
				$str = str_replace("Awps", $namespace, $str);
				$str = str_replace("awps", $namespace, $str);
			}
			
			if (! is_null($folderName)) {
				$str = str_replace("Alecaddd WordPress Starter theme", $folderName, $str);
			}

			file_put_contents($file, $str);
		}

		return $this;
	}

	private function recursiveScanFiles($path, &$file_info)
	{
		$path = rtrim($path, '/');
		if (! is_dir($path)) {
			$file_info[] = $path;
		} else {
			$files = scandir($path);
			foreach ($files as $file) {
				if ($file != '.' && $file != '..') {
					$this->recursiveScanFiles($path . '/' . $file, $file_info);
				}
			}
		}
	}

	private function cleanUp($ZipFile)
	{
		@chmod($ZipFile, 0777);
		@unlink($ZipFile);

		return $this;
	}

	private function moveConfig($directory, OutputInterface $output)
	{
		if (! empty(shell_exec("mv ".$directory."/wp-config.sample.php ../../"))) {
			$output->writeln('<comment>Unable to move the wp-config.sample.php file, be sure to move it in the base directory!</comment>');
		}

		if (is_file("../../.env")) {
			if (! empty(shell_exec("mv ../../.env ../../.env.bk"))) {
				$output->writeln('<comment>Existing .env detected in the WordPress root directory, unable to create a safe backup!</comment>');
				return $this;
			}
		}

		if (! empty(shell_exec("mv ".$directory."/.env.example ../../.env"))) {
			$output->writeln('<comment>Unable to move the .env.example file, be sure to move it in the base directory and rename it as .env!</comment>');
		}

		return $this;
	}
}

<?php

namespace Infira\pmg;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;
use Infira\pmg\helper\ConsoleOutput;

class Command extends \Symfony\Component\Console\Command\Command
{
	public ConsoleOutput     $output;
	protected InputInterface $input;
	
	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		set_time_limit(7200);
		$this->output = &$output;
		$this->input  = &$input;
		$this->beforeExecute();
		$this->runCommand();
		$this->afterExecute();
		
		return $this->success();
	}
	
	public function error(string $msg)
	{
		$this->output->error($msg);
		exit;
	}
	
	public function region(string $region, callable $regionProcess)
	{
		$msg = str_repeat("=", 25);
		$msg .= "[<question> $region </question>]";
		$msg .= str_repeat("=", 25);
		$this->output->comment($msg);
		$this->output->nl();
		$regionProcess();
		$this->output->nl();
		$this->output->comment($msg);
	}
	
	public function processRegionCommand(string $regionName, string $command)
	{
		$this->region($regionName, function () use ($regionName, $command)
		{
			$sectiion = $this->output->section();
			$process  = Process::fromShellCommandline($command);
			$process->setTimeout(1800);
			$process->start();
			$process->wait(function ($type, $buffer) use ($regionName, $sectiion)
			{
				$buffer = trim($buffer);
				if (str_contains($buffer, '%'))
				{
					$sectiion->overwrite("<comment>$regionName</comment>: " . $buffer);
					//$this->output->cl()->write("<comment>$regionName</comment>: " . trim($buffer));
					//$this->output->cl()->msg($line);
					//$this->output->cl()->write($line);
				}
				else
				{
					$this->output->msg($buffer);
				}
			});
		});
	}
	
	protected function success(): int
	{
		return \Symfony\Component\Console\Command\Command::SUCCESS;
	}
	
	protected function beforeExecute() {}
	
	protected function runCommand() {}
	
	protected function afterExecute()
	{
		//void
	}
}
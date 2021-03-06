<?php


namespace tests\Dudulina\CodeGeneration;


use Dudulina\CodeGeneration\CommandValidatorsMapCodeGenerator;
use Gica\FileSystem\FileSystemInterface;
use Gica\FileSystem\InMemoryFileSystem;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\Command1;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\Command2;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\CommandValidatorSubscriber;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\CommandValidatorSubscriberTemplate;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\ValidatorForCommand1;
use tests\Dudulina\CodeGeneration\CommandValidatorsMapCodeGeneratorData\ValidatorForCommand2;


class CommandValidatorsMapCodeGeneratorTest extends \PHPUnit_Framework_TestCase
{

    const EXPECTED_MAP = [
        Command2::class => [
            [
                ValidatorForCommand2::class,
                'validateCommand2',
            ],
        ],
        Command1::class => [
            [
                ValidatorForCommand1::class,
                'validateCommand1',
            ],
        ],
    ];

    public function test()
    {
        $fileSystem = $this->stubFileSystem();

        $sut = new CommandValidatorsMapCodeGenerator(
            $this->mockLogger(),
            $fileSystem
        );

        $sut->generate(
            CommandValidatorSubscriberTemplate::class,
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/CommandValidatorsMapCodeGeneratorData')),
            __DIR__ . '/CommandValidatorsMapCodeGeneratorData/CommandValidatorSubscriber.php',
            'CommandValidatorSubscriber'
        );

        $this->evaluateGeneratedClass($fileSystem);

        /** @noinspection PhpUndefinedClassInspection */
        $mapper = new CommandValidatorSubscriber();

        $map = $mapper->getCommandHandlersDefinitions();

        $this->assertCount(2, $map);

        $this->assertEquals(self::EXPECTED_MAP[Command1::class], $map[Command1::class]);
        $this->assertEquals(self::EXPECTED_MAP[Command2::class], $map[Command2::class]);

    }

    private function mockLogger()
    {
        $logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)
            ->getMock();
        return $logger;
        /** @var \Psr\Log\LoggerInterface $logger */
    }

    private function evaluateGeneratedClass(FileSystemInterface $fileSystem)
    {
        if (class_exists(CommandValidatorSubscriber::class)) {
            return;
        }

        $content = $fileSystem->fileGetContents(__DIR__ . '/CommandValidatorsMapCodeGeneratorData/CommandValidatorSubscriber.php');
        $content = str_replace('<?php', '', $content);
        eval($content);
    }

    private function stubFileSystem(): InMemoryFileSystem
    {
        $fileSystem = new InMemoryFileSystem();

        $fileSystem->makeDirectory(__DIR__ . '/CommandValidatorsMapCodeGeneratorData', 0777, true);
        $fileSystem->filePutContents(
            __DIR__ . '/CommandValidatorsMapCodeGeneratorData/CommandValidatorSubscriberTemplate.php',
            file_get_contents(__DIR__ . '/CommandValidatorsMapCodeGeneratorData/CommandValidatorSubscriberTemplate.php'));
        return $fileSystem;
    }
}

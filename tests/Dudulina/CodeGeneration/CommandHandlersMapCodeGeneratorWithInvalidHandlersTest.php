<?php


namespace tests\Dudulina\CodeGeneration;


use Dudulina\CodeGeneration\CommandHandlersMapCodeGenerator;
use Gica\FileSystem\InMemoryFileSystem;
use tests\Dudulina\CodeGeneration\CommandHandlersMapCodeGeneratorWithInvalidHandlersData\CommandHandlersMap;
use tests\Dudulina\CodeGeneration\CommandHandlersMapCodeGeneratorWithInvalidHandlersData\CommandHandlersMapTemplate;


class CommandHandlersMapCodeGeneratorWithInvalidHandlersTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {

        $fileSystem = $this->stubFileSystem();

        $sut = new CommandHandlersMapCodeGenerator(
            $this->mockLogger(),
            $fileSystem
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('#multiple handlers exists for command#ims');

        $sut->generate(
            CommandHandlersMapTemplate::class,
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/CommandHandlersMapCodeGeneratorWithInvalidHandlersData')),
            __DIR__ . '/CommandHandlersMapCodeGeneratorWithInvalidHandlersData/CommandHandlersMap.php',
            'CommandHandlersMap'
        );
    }

    private function mockLogger()
    {
        $logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)
            ->getMock();
        return $logger;
        /** @var \Psr\Log\LoggerInterface $logger */
    }

    private function stubFileSystem(): InMemoryFileSystem
    {
        $fileSystem = new InMemoryFileSystem();

        $fileSystem->makeDirectory(__DIR__ . '/CommandHandlersMapCodeGeneratorWithInvalidHandlersData', 0777, true);
        $fileSystem->filePutContents(
            __DIR__ . '/CommandHandlersMapCodeGeneratorWithInvalidHandlersData/CommandHandlersMapTemplate.php',
            file_get_contents(__DIR__ . '/CommandHandlersMapCodeGeneratorWithInvalidHandlersData/CommandHandlersMapTemplate.php'));
        return $fileSystem;
    }
}

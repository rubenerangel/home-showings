<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use UnitEnum;

/** Criterion 45: the design rules of CLAUDE.md that can be read from the code. */
class ArchitectureTest extends TestCase
{
    private const APP = __DIR__.'/../../app';

    public function test_an_injected_service_can_be_replaced(): void
    {
        $services = self::services();

        $this->assertContains('App\Scheduling\BookShowing', $services, 'Injected in a controller method.');
        $this->assertContains('App\Scheduling\TellOtherSide', $services, 'Injected in a constructor.');
        $this->assertSame([], array_values(array_filter($services, fn (string $class) => (new ReflectionClass($class))->isFinal())));
    }

    #[DataProvider('classes')]
    public function test_services_are_injected_and_not_looked_up(string $class): void
    {
        $code = self::code((new ReflectionClass($class))->getFileName());

        $this->assertDoesNotMatchRegularExpression('/(?<![\w>:$\\\\])(app|resolve)\(|App::make|Container::getInstance/', $code);
        foreach (self::services() as $service) {
            $this->assertStringNotContainsString('new '.(new ReflectionClass($service))->getShortName().'(', $code);
        }
    }

    #[DataProvider('classes')]
    public function test_static_methods_do_not_touch_the_database(string $class): void
    {
        $file = file((new ReflectionClass($class))->getFileName());
        $static = array_filter(
            (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_STATIC),
            fn (ReflectionMethod $method) => $method->class === $class && $method->getFileName() === (new ReflectionClass($class))->getFileName(),
        );

        foreach ($static as $method) {
            $code = implode(array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
            $this->assertDoesNotMatchRegularExpression('/DB::|::query\(|->(exists|get|first|firstOrFail|findOrFail|create|update|delete|save|notify)\(/', $code, $method->name);
        }
        $this->addToAssertionCount(1);
    }

    #[DataProvider('controllers')]
    public function test_a_controller_does_not_write_and_only_looks_up_by_id(string $class): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/DB::|::query\(|::create\(|[A-Z]\w*::(find\w*|firstOrFail|all)\(|->(create|update|delete|save|insert|with|orderBy|where\w*|exists|get|first|firstOrFail|count|lockForUpdate)\(/',
            self::code((new ReflectionClass($class))->getFileName()),
        );
    }

    /** @return array<string, array{class-string}> */
    public static function classes(): array
    {
        $cases = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::APP, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $class = 'App\\'.str_replace('/', '\\', substr($file->getPathname(), strlen(self::APP) + 1, -4));
            $cases[$class] = [$class];
        }
        ksort($cases);

        return $cases;
    }

    /** @return array<string, array{class-string}> */
    public static function controllers(): array
    {
        return array_filter(self::classes(), fn (array $case) => str_contains($case[0], '\Controllers\\'));
    }

    /** @return list<class-string> what constructors and controller methods receive, without values, models, enums and exceptions */
    private static function services(): array
    {
        $services = [];
        foreach (array_column(self::classes(), 0) as $class) {
            $reflection = new ReflectionClass($class);
            $methods = str_contains($class, '\Controllers\\') ? $reflection->getMethods() : array_filter([$reflection->getConstructor()]);
            foreach ($methods as $method) {
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if (! $type instanceof ReflectionNamedType || ! str_starts_with($type->getName(), 'App\\')) {
                        continue;
                    }
                    $injected = new ReflectionClass($type->getName());
                    $isValue = $injected->isReadOnly() || array_any([Model::class, UnitEnum::class, Throwable::class], fn (string $type) => $injected->isSubclassOf($type));
                    if (! $isValue) {
                        $services[$injected->name] = true;
                    }
                }
            }
        }

        return array_keys($services);
    }

    /** Without comments. */
    private static function code(string $file): string
    {
        return php_strip_whitespace($file);
    }
}

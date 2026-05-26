<?php
namespace App\Tests\Form;

use App\Entity\AgentToken;
use App\Entity\Environment;
use App\Form\AgentTokenType;
use App\Repository\EnvironmentRepository;
use App\Service\TenantContext;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class AgentTokenTypeTest extends TypeTestCase
{
    private static function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }

    protected function getExtensions(): array
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $manager = $this->createMock(ObjectManager::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new PreloadedExtension([
                new AgentTokenType(
                    $this->createMock(TenantContext::class),
                    $this->createMock(EnvironmentRepository::class)
                ),
                new EntityType($registry),
            ], []),
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $environment = new Environment();
        self::setEntityId($environment, 1);
        $environment->setName('Production');

        $token = new AgentToken();
        $token->setEnvironment($environment);

        $formData = [
            'name' => 'Test Token',
            'environment' => 1,
            'isActive' => true,
        ];

        $form = $this->factory->create(AgentTokenType::class, $token, ['environments' => [$environment]]);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals('Test Token', $token->getName());
        $this->assertTrue($token->isActive());
    }

    public function testFormRendersCorrectly(): void
    {
        $environment = new Environment();
        self::setEntityId($environment, 1);
        $environment->setName('Production');

        $token = new AgentToken();
        $token->setEnvironment($environment);

        $form = $this->factory->create(AgentTokenType::class, $token, ['environments' => [$environment]]);

        $view = $form->createView();
        $children = $view->children;

        $this->assertArrayHasKey('name', $children);
        $this->assertArrayHasKey('environment', $children);
        $this->assertArrayHasKey('isActive', $children);
    }

    public function testSubmitWithMissingName(): void
    {
        $environment = new Environment();
        self::setEntityId($environment, 1);
        $environment->setName('Production');

        $token = new AgentToken();
        $token->setEnvironment($environment);

        $formData = [
            'name' => '',
            'environment' => 1,
            'isActive' => true,
        ];

        $form = $this->factory->create(AgentTokenType::class, $token, ['environments' => [$environment]]);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $errors = $form->getErrors(true);
        $this->assertCount(1, $errors);
    }
}

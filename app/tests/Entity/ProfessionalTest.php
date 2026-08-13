<?php

namespace App\Tests\Entity;

use App\Entity\Professional;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfessionalTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    private function makePro(string $domaine, ?string $nomResponsable, ?string $prenomResponsable): Professional
    {
        $pro = new Professional();
        $pro->setNomSociete('Test');
        $pro->setDomaineActivite($domaine);
        $pro->setEmail('test' . uniqid() . '@example.com');
        $pro->setNomResponsable($nomResponsable);
        $pro->setPrenomResponsable($prenomResponsable);

        return $pro;
    }

    public function testNomResponsableRequiredForSante(): void
    {
        $pro = $this->makePro('Santé', null, null);

        $violations = $this->validator->validate($pro, null, ['Default']);
        $messages = $this->violationPaths($violations);

        $this->assertContains('nomResponsable', $messages);
        $this->assertContains('prenomResponsable', $messages);
    }

    public function testNomResponsableRequiredForDroitEtConseil(): void
    {
        $pro = $this->makePro('Droit & Conseil', null, null);

        $violations = $this->validator->validate($pro, null, ['Default']);

        $this->assertContains('nomResponsable', $this->violationPaths($violations));
    }

    public function testNomResponsableNotRequiredForAlimentation(): void
    {
        // Un épicier / boucher n'a pas à donner son identité personnelle — seul le nom
        // de la société compte. Cf. demande utilisateur du 2026-08-12.
        $pro = $this->makePro('Alimentation', null, null);

        $violations = $this->validator->validate($pro, null, ['Default']);

        $this->assertNotContains('nomResponsable', $this->violationPaths($violations));
        $this->assertNotContains('prenomResponsable', $this->violationPaths($violations));
    }

    public function testNomResponsableSatisfiesConstraintWhenProvided(): void
    {
        $pro = $this->makePro('Santé', 'Dupont', 'Jean');

        $violations = $this->validator->validate($pro, null, ['Default']);

        $this->assertNotContains('nomResponsable', $this->violationPaths($violations));
        $this->assertNotContains('prenomResponsable', $this->violationPaths($violations));
    }

    public function testEcommerceTypeNeverRequiresResponsable(): void
    {
        // Le callback ne s'applique qu'au type PHYSIQUE — une boutique en ligne n'a pas
        // de champ SIRET ni de responsable nommé.
        $pro = $this->makePro('Santé', null, null);
        $pro->setType(Professional::TYPE_ECOMMERCE);

        $violations = $this->validator->validate($pro, null, ['Default']);

        $this->assertNotContains('nomResponsable', $this->violationPaths($violations));
    }

    private function violationPaths(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): array
    {
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}

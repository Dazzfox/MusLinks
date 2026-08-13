<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('auteur', TextType::class, [
                'label' => 'Votre prénom ou initiales',
                'required' => false,
                // Sans ça, un champ laissé vide transmet null à Review::setAuteur(string),
                // qui n'accepte pas null (TypeError → 500) — Review::$auteur n'est jamais
                // censé être nul, seulement vide (le contrôleur retombe ensuite sur "Anonyme").
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'Anonyme',
                    'class' => 'form-input',
                ],
            ])
            ->add('note', HiddenType::class)
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre avis',
                'attr' => [
                    'placeholder' => 'Décrivez votre expérience avec ce professionnel…',
                    'rows' => 4,
                    'class' => 'form-input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}

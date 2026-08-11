<?php

namespace App\Form;

use App\Entity\Professional;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Vich\UploaderBundle\Form\Type\VichImageType;

class EcommerceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomSociete', TextType::class, [
                'label' => 'Nom de la boutique *',
                'attr'  => ['placeholder' => 'Ex : Épicerie Baraka, Robes Élégance…', 'class' => 'form-input'],
            ])
            ->add('domaineActivite', ChoiceType::class, [
                'label' => "Catégorie *",
                'choices' => [
                    'Alimentation & Épicerie'    => 'Alimentation & Épicerie',
                    'Vêtements & Mode'            => 'Vêtements & Mode',
                    'Beauté & Cosmétiques'        => 'Beauté & Cosmétiques',
                    'Maison & Décoration'         => 'Maison & Décoration',
                    'Bijoux & Accessoires'        => 'Bijoux & Accessoires',
                    'Librairie & Livres'          => 'Librairie & Livres',
                    'Voyance & Spirituel'         => 'Voyance & Spirituel',
                    'Informatique & High-tech'    => 'Informatique & High-tech',
                    'Sport & Outdoor'             => 'Sport & Outdoor',
                    'Jouets & Enfants'            => 'Jouets & Enfants',
                    'Santé & Bien-être'           => 'Santé & Bien-être',
                    'Autre'                       => 'Autre',
                ],
                'placeholder' => 'Choisissez une catégorie',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('siteEcommerce', UrlType::class, [
                'label'    => 'URL de votre boutique en ligne *',
                'required' => true,
                'attr'     => ['placeholder' => 'https://monshop.fr', 'class' => 'form-input'],
                'default_protocol' => 'https',
                'constraints' => [
                    new NotBlank(message: "L'URL de votre boutique est obligatoire."),
                    new Url(message: "L'URL n'est pas valide."),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email de contact *',
                'attr'  => ['placeholder' => 'contact@monshop.fr', 'class' => 'form-input'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description de votre boutique',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Décrivez vos produits, votre univers, ce qui vous rend unique…',
                    'rows'        => 4,
                    'class'       => 'form-input',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label'        => 'Logo / Visuel de la boutique',
                'required'     => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri'    => false,
                'attr'         => ['class' => 'form-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => Professional::class,
            'validation_groups' => ['Default', 'ecommerce'],
        ]);
    }
}

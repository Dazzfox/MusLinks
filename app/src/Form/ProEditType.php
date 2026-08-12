<?php

namespace App\Form;

use App\Entity\Professional;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProEditType extends AbstractType
{
    private const CHOICES_PHYSIQUE = [
        'Santé'                 => 'Santé',
        'Droit & Conseil'       => 'Droit & Conseil',
        'Alimentation'          => 'Alimentation',
        'Bâtiment & Travaux'    => 'Bâtiment & Travaux',
        'Informatique & Tech'   => 'Informatique & Tech',
        'Beauté & Bien-être'    => 'Beauté & Bien-être',
        'Éducation & Formation' => 'Éducation & Formation',
        'Finance & Assurance'   => 'Finance & Assurance',
        'Immobilier'            => 'Immobilier',
        'Autre'                 => 'Autre',
    ];

    private const CHOICES_ECOMMERCE = [
        'Alimentation & Épicerie'  => 'Alimentation & Épicerie',
        'Vêtements & Mode'         => 'Vêtements & Mode',
        'Beauté & Cosmétiques'     => 'Beauté & Cosmétiques',
        'Maison & Décoration'      => 'Maison & Décoration',
        'Bijoux & Accessoires'     => 'Bijoux & Accessoires',
        'Librairie & Livres'       => 'Librairie & Livres',
        'Voyance & Spirituel'      => 'Voyance & Spirituel',
        'Informatique & High-tech' => 'Informatique & High-tech',
        'Sport & Outdoor'          => 'Sport & Outdoor',
        'Jouets & Enfants'         => 'Jouets & Enfants',
        'Santé & Bien-être'        => 'Santé & Bien-être',
        'Autre'                    => 'Autre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Professional|null $pro */
        $pro = $builder->getData();
        $isEcommerce = $pro instanceof Professional && $pro->isEcommerce();

        $builder->add('nomSociete', TextType::class, [
            'label' => $isEcommerce ? 'Nom de la boutique' : 'Nom de la société',
            'attr'  => ['class' => 'form-input'],
        ]);

        $builder->add('domaineActivite', ChoiceType::class, [
            'label'   => $isEcommerce ? 'Catégorie' : "Domaine d'activité",
            'choices' => $isEcommerce ? self::CHOICES_ECOMMERCE : self::CHOICES_PHYSIQUE,
            'attr'    => ['class' => 'form-input'],
        ]);

        if (!$isEcommerce) {
            $builder
                ->add('prenomResponsable', TextType::class, [
                    'label'    => 'Prénom du responsable',
                    'required' => false,
                    'attr'     => ['class' => 'form-input'],
                ])
                ->add('nomResponsable', TextType::class, [
                    'label'    => 'Nom du responsable',
                    'required' => false,
                    'attr'     => ['class' => 'form-input'],
                ])
                ->add('profession', TextType::class, [
                    'label' => 'Profession / Titre',
                    'attr'  => ['class' => 'form-input'],
                ])
                ->add('telephone', TelType::class, [
                    'label' => 'Téléphone',
                    'attr'  => ['class' => 'form-input'],
                ])
                ->add('genre', ChoiceType::class, [
                    'label'       => 'Genre du responsable',
                    'required'    => false,
                    'placeholder' => 'Non précisé',
                    'choices'     => ['Homme' => 'M', 'Femme' => 'F'],
                    'attr'        => ['class' => 'form-input'],
                ])
                ->add('siteWeb', UrlType::class, [
                    'label'            => 'Site web',
                    'required'         => false,
                    'attr'             => ['placeholder' => 'https://www.masociete.fr', 'class' => 'form-input'],
                    'default_protocol' => 'https',
                ])
                ->add('siteEcommerce', UrlType::class, [
                    'label'            => 'Boutique en ligne (optionnel)',
                    'required'         => false,
                    'attr'             => ['placeholder' => 'https://monshop.fr', 'class' => 'form-input'],
                    'default_protocol' => 'https',
                ]);
        } else {
            $builder->add('siteEcommerce', UrlType::class, [
                'label'            => 'URL de la boutique',
                'required'         => true,
                'attr'             => ['placeholder' => 'https://monshop.fr', 'class' => 'form-input'],
                'default_protocol' => 'https',
            ]);
        }

        $builder
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 5, 'class' => 'form-input'],
            ])
            ->add('imageFile', VichImageType::class, [
                'label'        => 'Photo / Logo',
                'required'     => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri'    => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Professional::class]);
    }
}

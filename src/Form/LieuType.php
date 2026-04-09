<?php

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\Offre;
use App\Enum\LieuCategorie;
use App\Enum\LieuType as LieuTypeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LieuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offre', EntityType::class, [
                'class' => Offre::class,
                'choice_label' => 'titre',
                'label' => 'Offre associée',
                'placeholder' => 'Aucune offre',
                'required' => false,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('siteWeb', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
            ])
            ->add('instagram', TextType::class, [
                'label' => 'Instagram',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                ],
            ])
            ->add('budgetMin', NumberType::class, [
                'label' => 'Budget minimum',
                'required' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => [
                    'step' => '0.01',
                    'min' => 0,
                ],
            ])
            ->add('budgetMax', NumberType::class, [
                'label' => 'Budget maximum',
                'required' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => [
                    'step' => '0.01',
                    'min' => 0,
                ],
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'placeholder' => 'Sélectionner une catégorie',
                'choices' => LieuCategorie::choices(),
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'scale' => 8,
                'html5' => false,
                'attr' => [
                    'step' => '0.00000001',
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'scale' => 8,
                'html5' => false,
                'attr' => [
                    'step' => '0.00000001',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'placeholder' => 'Sélectionner un type',
                'choices' => LieuTypeEnum::choices(),
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'Image URL',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lieu::class,
            'csrf_token_id' => 'lieu_form',
        ]);
    }
}

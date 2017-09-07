<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 27.12.16
 * Time: 13:40
 */

namespace AppBundle\Form;


use AppBundle\Entity\Upload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type',ChoiceType::class,[
                'choices' => [
                    "form.choice.attributes" => 'attributes',
                    "form.choice.options" => 'options'
                ],
                "label" => "form.choice.label",
            ])
            ->add('lang',ChoiceType::class,[
                'choices' => $options['languages'],
                'label' => 'form.lang.label'
            ])
            ->add('filename',FileType::class,[
                'label' => 'form.choice.file.label',
                'attr' => [
                    'class' => 'inputfile',
                    'accept' => '.csv'
                ],
                'label_attr' => [
                    'class' => 'button button-bg',
                ]
            ])
        ;
    }

    /**
     *
    */
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Upload::class,
            'languages' => null,
        ]);
    }
}
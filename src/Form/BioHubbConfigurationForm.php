<?php 

namespace Drupal\bio_hubb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

class BioHubbConfigurationForm extends ConfigFormBase{

   /**
    * {@inheritdoc}
    */
    public function getFormId()
    {
        return 'bio_hubb_admin_settings';
    }
     
    /**
    * {@inheritdoc}
    */
    protected function getEditableConfigNames() {
        return [
            'bio_hubb.settings',
        ];
    }

    public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state)
    {
        $config = $this->config('bio_hubb.settings');
        $state = \Drupal::state();
        $form["#attributes"]["autocomplete"] = "off";
        $form["bio_hubb"] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Bio Hubb Settings')
        );
        $form['bio_hubb']['url'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Bio Hubb API URL'),
            '#default_value' => $config->get('bio_hubb.url'),
        );
        $form['bio_hubb']['client_id'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Client Id'),
            '#default_value' => $config->get('bio_hubb.client_id'),
        );
        $form['bio_hubb']['scope'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('scope'),
            '#default_value' => $config->get('bio_hubb.scope'),
        );
        $form['bio_hubb']['client_secret'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('client_secret'),
            '#default_value' => $config->get('bio_hubb.client_secret'),
        ); 
        $form['bio_hubb']['access_token'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('access_token'),
            '#default_value' => $config->get('bio_hubb.access_token'),
        ); 
        return parent::buildForm($form, $form_state);       
    }

    public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $config = $this->config('bio_hubb.settings');
        $state  = \Drupal::state();
        $config->set('bio_hubb.url', $values['url']);
        $config->set('bio_hubb.client_id', $values['client_id']);
        $config->set('bio_hubb.scope', $values['scope']);
        $config->set('bio_hubb.client_secret', $values['client_secret']);
        $config->save();
    }

}
<?php

namespace Drupal\multistep_register_form\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\multistep_register_form\MultiStepRegisterStorageManager;
use Egulias\EmailValidator\EmailValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MultiStepRegistrationForm.
 */
class MultiStepRegistrationForm extends FormBase {

  /**
   * The entity type manager.
   *
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The email validator.
   *
   * @var \Egulias\EmailValidator\EmailValidator
   */
  protected $emailValidator;

  /**
   * The multistep register form manager.
   *
   * @var \Drupal\multistep_register_form\MultiStepRegisterStorageManager
   */
  protected $registerStorage;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var string The form wrapper ID.
   */
  protected $formWrapperId;

  /**
   * Constructs a new MultiStepRegistrationForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Egulias\EmailValidator\EmailValidator $email_validator
   *   The email validator.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EmailValidator $email_validator,
    MultiStepRegisterStorageManager $register_storage,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->emailValidator = $email_validator;
    $this->registerStorage = $register_storage;
    $this->messenger = $messenger;
    $this->formWrapperId = Html::getId('multistep-registration-ajax');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('email.validator'),
      $container->get('multistep_register_form.storage_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_step_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the stored values from the storage.
    $values = $form_state->getStorage();
    $current_step = $form_state->get('current_step') ?? 1;
    $form_state->set('current_step', $current_step);
    // Set the latest step to know when to show the buttons.
    $latest_step = 3;
    $form['#prefix'] = "<div id='$this->formWrapperId'>";
    $form['#suffix'] = '</div>';

    // Step 1 fields.
    $form['information'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="' . Html::getClass('multistep-registration-information') . '">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Step @current out of @total.', [
        '@current' => $current_step,
        '@total' => $latest_step,
      ])
    ];
    $form['email_messages'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="' . Html::getId('multistep-email-messages') . '">',
      '#suffix' => '</div>',
      '#markup' => '',
    ];

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#step' => 1,
      '#prefix' => '<div id="' . Html::getId('multistep-email') . '">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'validateEmailCallback'],
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying email...'),
        ],
      ],
    ];

    $form['field_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#step' => 1,
    ];

    $form['field_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#step' => 1,
    ];

    $form['field_gender'] = [
      '#type' => 'radios',
      '#title' => $this->t('Gender'),
      '#required' => TRUE,
      '#step' => 1,
      '#options' => [
        'M' => 'M',
        'F' => 'F',
      ],
    ];

    // Step 2 fields.
    $form['field_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#step' => 2,
    ];
    $form['field_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#step' => 2,
    ];
    $form['field_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#step' => 2,
    ];
    // Step 3 fields.
    $form['final_message'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="' . Html::getClass('multistep-registration-final-message') . '">',
      '#suffix' => '</div>',
      '#markup' => $this->t('We are ready. Click on finish to create the new user.'),
      '#step' => 3,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    // Show the 'Back' and the 'Reset' buttons when the step is greater than 1.
    if ($current_step > 1) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'wrapper' => $this->formWrapperId,
          'callback' => [$this, 'ajaxCallback'],
        ],
        '#submit' => ['::resetFormStep'],
      ];

      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'wrapper' => $this->formWrapperId,
          'callback' => [$this, 'ajaxCallback'],
        ],
        '#submit' => ['::decreaseFormStep'],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Finish'),
      '#ajax' => [
        'wrapper' => $this->formWrapperId,
        'callback' => [$this, 'ajaxCallback'],
      ],
    ];
    // If this is not the latest step, use a different submit callback.
    if ($latest_step !== $current_step) {
      $form['actions']['submit']['#submit'] = ['::increaseFormStep'];
      $form['actions']['submit']['#value'] = $this->t('Forward');
    }
    // Set the default values for the form elements.
    self::setDefaultValuesFromFormState($form, $values);
    // Hide the form elements that doesn't belong to the current step.
    self::hideFormFieldsNoStep($form, $current_step);

    return $form;
  }

  /**
   * Ajax callback that validates the email is valid and is free to use.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validateEmailCallback($form, FormStateInterface $form_state) {
    // Get the email field and email messages elements.
    $element = $form['mail'];
    $messages = $form['email_messages'];
    $email = $form_state->getValue('mail');
    if (!empty($email)) {
      // Validate the email is valid; if not, show an error and mark the field
      // with the class 'error'.
      if (!$this->emailValidator->isValid($email)) {
        $messages['#markup'] = $this->t('<div class="messages messages--error">The email %email is not valid.</div>', ['%email' => $email]);
        $element['#attributes']['class'][] = 'error';
      }
      // Validate the email is not taken; if not, show an error and mark the
      // field with the class 'error'.
      if ($this->validateExistingEmail($email)) {
        $messages['#markup'] = $this->t('<div class="messages messages--error">The email %email is already taken.</div>', ['%email' => $email]);
        $element['#attributes']['class'][] = 'error';
      }
    }
    // Add the needed replace commands.
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#multistep-email', $element));
    $response->addCommand(new ReplaceCommand('#multistep-email-messages', $messages));
    $form_state->setRebuild(TRUE);
    return $response;
  }

  /**
   * Sets the default value of the form elements using the form storage.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected static function setDefaultValuesFromFormState(array &$form, array $values) {
    $children = Element::getVisibleChildren($form);
    foreach ($children as $child) {
      if (isset($values[$child])) {
        $form[$child]['#default_value'] = $values[$child];
      }
    }
  }

  /**
   * Returns the complete form inside the ajax wrapper.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The complete form.
   */
  public function ajaxCallback($form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Submit callback that resets the Form state. Also sets the current step to
   * one.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function resetFormStep($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $form_state->setStorage([]);
    $form_state->set('current_step', 1);
  }

  /**
   * Submit callback that increases the Form current step to go one step
   * forward.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function increaseFormStep($form, FormStateInterface $form_state) {
    $current_step = $form_state->get('current_step');
    $current_step++;
    $form_state->cleanValues();
    $values = $form_state->getValues();
    $form_state->setRebuild();
    $form_state->set('current_step', $current_step);
    foreach ($values as $key => $value) {
      $form_state->set($key, $value);
    }
  }

  /**
   * Submit callback that decreases the Form current step to go one step back.
   *
   * @param $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function decreaseFormStep($form, FormStateInterface $form_state) {
    $current_step = $form_state->get('current_step');
    --$current_step;
    $form_state->setRebuild();
    $form_state->set('current_step', $current_step);
  }

  /**
   * Hides the elements that are not set to be shown in the current step.
   *
   * @param array $form
   * @param int $current_step
   */
  protected static function hideFormFieldsNoStep(array &$form, int $current_step) {
    $children = Element::getVisibleChildren($form);
    foreach ($children as $child) {
      if (isset($form[$child]['#step']) && $form[$child]['#step'] !== $current_step) {
        $form[$child]['#access'] = FALSE;
      }
    }
  }

  /**
   * Validates an email is not already in use by another user.
   *
   * @param string $email
   *   The email to be validated.
   *
   * @return bool
   *   Returns TRUE if the email is already taken.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function validateExistingEmail(string $email) {
    $exists = FALSE;
    if ($email) {
      $email = strtolower($email);
      $query = $this->entityTypeManager->getStorage('user')->getQuery()
        ->condition('mail', $email)
        ->execute();
      if ($query){
        $exists = TRUE;
      }
    }
    return $exists;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the Email is not taken.
    $email = $form_state->getValue('mail');
    if (!empty($email) && $this->validateExistingEmail($email)) {
      $form_state->setError($form['mail'], $this->t('The email %email is already taken.', ['%email' => $email]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $form_state->setStorage([]);
    $form_state->cleanValues();
    $values = $form_state->getValues();
    $mail = $values['mail'];
    // Insert the values in the table.
    $this->registerStorage->insert($values);
    // Create the new user.
    $user = $this->entityTypeManager->getStorage('user')->create(['mail' => $mail]);
    $result = $user->save();
    // Display result.
    if ($result) {
      $this->messenger
        ->addMessage($this->t('The new user %email has been created.', ['%email' => $mail]));
    }
  }

}

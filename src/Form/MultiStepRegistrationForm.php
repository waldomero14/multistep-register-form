<?php

namespace Drupal\multistep_register_form\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class MultiStepRegistrationForm.
 */
class MultiStepRegistrationForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected  $formWrapperId;

  /**
   * Constructs a new MultiStepRegistrationForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formWrapperId = Html::getId('multistep-registration-ajax');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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
    $values = $form_state->getStorage();
    $current_step = $form_state->get('current_step') ?? 1;
    $form_state->set('current_step', $current_step);
    dpm('storafe');
    dpm($form_state->getStorage());
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
      '#prefix' => "<div id='multistep-email-messages'>",
      '#suffix' => '</div>',
      '#markup' => '',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#step' => 1,
      '#prefix' => '<div id="' . Html::getId('multistep-email') . '">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'validateExistingEmail'],
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying email...'),
        ],
      ],
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#step' => 1,
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#step' => 1,
    ];

    $form['gender'] = [
      '#type' => 'radios',
      '#title' => $this->t('Gender'),
      '#required' => TRUE,
      '#step' => 1,
      '#options' => ['M', 'F'],
    ];

    // Step 2 fields.
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#step' => 2,
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#step' => 2,
    ];
    $form['address'] = [
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

    // Show the 'Back' and the 'Reset' buttons when the step is greater than 1.
    if ($current_step > 1) {
      $form['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'wrapper' => $this->formWrapperId,
          'callback' => [$this, 'ajaxCallback'],
        ],
        '#submit' => ['::resetFormStep'],
      ];

      $form['back'] = [
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

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Finish'),
      '#ajax' => [
        'wrapper' => $this->formWrapperId,
        'callback' => [$this, 'ajaxCallback'],
      ],
    ];
    if ($latest_step !== $current_step) {
      $form['submit']['#submit'] = ['::increaseFormStep'];
      $form['submit']['#value'] = $this->t('Forward');
    }

    self::setDefaultValuesFromFormState($form, $values);

    self::hideFormFieldsNoStep($form, $current_step);

    return $form;
  }

  public function validateExistingEmail($form, FormStateInterface $form_state) {
    $element = $form['email'];
    $messages = $form['email_messages'];
    $email = $form_state->getValue('email');
    dpm($email);
    if (!empty($email) && !\Drupal::service('email.validator')->isValid($email)) {
      $messages['#markup'] = $this->t('<div class="messages messages--error">The email is not valid.</div>');
      $element['#attributes']['class'][] = 'error';
    }
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#multistep-email', $element));
    $response->addCommand(new ReplaceCommand('#multistep-email-messages', $messages));
    $form_state->setRebuild(TRUE);
    return $response;
  }

  protected static function setDefaultValuesFromFormState(array &$form, array $values) {
    $children = Element::getVisibleChildren($form);
    foreach ($children as $child) {
      if (isset($values[$child])) {
        $form[$child]['#default_value'] = $values[$child];
      }
    }
  }

  public function ajaxCallback($form, FormStateInterface $form_state): array {
    return $form;
  }

  public static function resetFormStep($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $form_state->setStorage([]);
    $form_state->set('current_step', 1);
  }

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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $form_state->setStorage([]);
    // Display result.
    \Drupal::messenger()->addMessage($this->t('The new user @email has been created.', ['@email' => $form_state->getValue('email')]));
  }

}

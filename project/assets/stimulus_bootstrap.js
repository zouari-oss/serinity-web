import { startStimulusApp } from '@symfony/stimulus-bundle';
import AuthSwitcherController from './controllers/auth_switcher_controller.js';
import FormFeedbackController from './controllers/form_feedback_controller.js';
import TableFilterController from './controllers/table_filter_controller.js';
import ModalController from './controllers/modal_controller.js';
import UiThemeController from './controllers/ui_theme_controller.js';
import PasswordToggleController from './controllers/password_toggle_controller.js';
import SubmitStateController from './controllers/submit_state_controller.js';
import AuthFormController from './controllers/auth_form_controller.js';

const app = startStimulusApp();

app.register('auth-switcher', AuthSwitcherController);
app.register('form-feedback', FormFeedbackController);
app.register('table-filter', TableFilterController);
app.register('modal', ModalController);
app.register('ui-theme', UiThemeController);
app.register('password-toggle', PasswordToggleController);
app.register('submit-state', SubmitStateController);
app.register('auth-form', AuthFormController);

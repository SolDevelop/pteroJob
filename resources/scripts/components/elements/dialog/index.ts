import DialogComponent from './Dialog';
import DialogFooter from './DialogFooter';
import DialogIcon from './DialogIcon';
import ConfirmationDialog from './ConfirmationDialog';

import QueueDialog from './QueueDialog';

const Dialog = Object.assign(DialogComponent, {
    Confirm: ConfirmationDialog,
    Queue: QueueDialog,
    Footer: DialogFooter,
    Icon: DialogIcon,
});

export { Dialog };
export * from './types.d';
export * from './context';
export { default as styles } from './style.module.css';

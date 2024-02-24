import React from 'react';
import { Dialog, RenderDialogProps } from '.';

type QueueDialog = Omit<RenderDialogProps, 'description' | 'children'> & {
    children: React.ReactNode;
};

export default ({ children, ...props }: QueueDialog) => {
    return (
        <Dialog {...props} description={typeof children === 'string' ? children : undefined}>
            {typeof children !== 'string' && children}
        </Dialog>
    );
};

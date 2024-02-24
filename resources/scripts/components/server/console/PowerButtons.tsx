import React, { useEffect, useState } from 'react';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import axios from 'axios';

interface PowerButtonProps {
    className?: string;
}

async function gettter(action: any) {
    const currentURL = window.location.href;

    // Find the last forward slash in the URL
    const lastSlashIndex = currentURL.lastIndexOf('/');

    // Extract everything after the last forward slash
    const serverID = currentURL.substring(lastSlashIndex + 1);
    const url = `http://localhost/api/client/servers/${serverID}/queue`;
    const token = 'ptla_PxiseR8eU1IahdQkrcppl4pyYJnHEhmBKeHS5EYOUZ5';

    const data = {
        input: action,
    };

    const config = {
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
    };

    try {
        await axios.post(url, data, config);
        return 200;
    } catch (error: any) {
        return error.response.status;
    }
}
async function starter() {
    const currentURL = window.location.href;

    // Find the last forward slash in the URL
    const lastSlashIndex = currentURL.lastIndexOf('/');

    // Extract everything after the last forward slash
    const serverID = currentURL.substring(lastSlashIndex + 1);
    const url = `http://localhost/api/client/servers/${serverID}/queue`;
    const token = 'ptla_PxiseR8eU1IahdQkrcppl4pyYJnHEhmBKeHS5EYOUZ5';

    const data = {
        input: 'start',
    };

    const config = {
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
    };

    try {
        await axios.post(url, data, config);
        return 200;
    } catch (error: any) {
        return error.response.status;
    }
}
export default ({ className }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const [waiting, setWaiting] = useState(false);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    let intervalId: NodeJS.Timeout;
    const runner = async () => {
        // Your task logic goes here
        const v = await starter();
        if (v === 200) {
            setWaiting(false);
            clearInterval(intervalId);
        } else {
            console.warn('Retrying');
        }
    };
    const killable = status === 'stopping';
    const onButtonClick = async (
        action: PowerAction | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): Promise<void> => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }
        if (instance) {
            setOpen(false);
            const v = await gettter(action);
            if (v === 401) {
                setWaiting(true);
                intervalId = setInterval(runner, 60000);
            }
            if (waiting === true) {
                setWaiting(false);
            }
        }
    };

    useEffect(() => {
        if (status === 'offline') {
            setOpen(false);
        }
    }, [status]);

    return (
        <div className={className}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop Process'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a server can lead to data corruption.
            </Dialog.Confirm>
            <Dialog.Queue open={waiting} hideCloseIcon={true} onClose={() => setWaiting(false)} title={'Queue'}>
                You are on a Queue, Please wait until there is a slot. Please Wait, you can close this page
            </Dialog.Queue>
            <Can action={'control.start'}>
                <Button
                    className={'flex-1'}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    Start
                </Button>
            </Can>
            <Can action={'control.restart'}>
                <Button.Text className={'flex-1'} disabled={!status} onClick={onButtonClick.bind(this, 'restart')}>
                    Restart
                </Button.Text>
            </Can>
            <Can action={'control.stop'}>
                <Button.Danger
                    className={'flex-1'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    {killable ? 'Kill' : 'Stop'}
                </Button.Danger>
            </Can>
        </div>
    );
};

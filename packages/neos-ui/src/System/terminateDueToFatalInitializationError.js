import logo from '@neos-project/react-ui-components/src/Logo/logo.svg';

import styles from '@neos-project/neos-ui-error/src/container/ErrorBoundary/style.module.css';

export function terminateDueToFatalInitializationError(reason) {
    if (!document.body) {
        throw new Error(reason);
    }

    document.title = 'The Neos UI could not be initialized.';
    document.body.innerHTML = `
        <div class="${styles.container}">
            <div>
                <img style="height: 24px; width: auto;" src='${logo}' alt="Neos" />
                <h1 class="${styles.title}">
                    Sorry, but the Neos UI could not be initialized.
                </h1>
                ${reason}
            </div>
        </div>
    `;

    throw new Error(document.body.innerText);
}

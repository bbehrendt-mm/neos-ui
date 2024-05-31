/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
import React from 'react';

import type {State} from '@neos-project/framework-observable';

export function useLatestState<V>(state$: State<V>) {
    const [value, setValue] = React.useState<V>(state$.current);

    React.useEffect(() => {
        const subscription = state$.subscribe({
            next: (incomingValue) => {
                if (incomingValue !== value) {
                    setValue(incomingValue);
                }
            }
        });

        return () => subscription.unsubscribe();
    }, [state$]);

    return value;
}

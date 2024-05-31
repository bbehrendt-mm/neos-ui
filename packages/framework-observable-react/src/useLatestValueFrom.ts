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

import type {Observable} from '@neos-project/framework-observable';

export function useLatestValueFrom<V>(
    observableFactory: () => Observable<V>,
    deps: any[] = []
) {
    const observable$ = React.useMemo(observableFactory, deps);
    const [value, setValue] = React.useState<null | V>(null);

    React.useEffect(() => {
        const subscription = observable$.subscribe({
            next: (incomingValue) => {
                if (incomingValue !== value) {
                    setValue(incomingValue);
                }
            }
        });

        return () => subscription.unsubscribe();
    }, [observable$]);

    return value;
}

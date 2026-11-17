# ArmorHub mobile app (iOS and Android)

A customer app for the platform, built with Expo (React Native) and Expo Router. It talks to the same API as the website
(`/api/v1`), so one account, one set of orders and one database: an order started in the app can be finished on the website and the other way round.

## What it does (phase 1: the customer)

- Browse the markets (stores), search a store's products, see a product page with the content the seller wrote.
- Sign in with email, Apple (iOS) or Google. Creating an account and resetting a password open the website.
- Order goods (delivery address, platform fee shown before paying, card payment in the secure browser) or request a quote for a service.
- Your orders and each order's status, pay a waiting order, push notifications that open the order.
- Delete the account from inside the app (required by both stores).

Not in this version: Facebook sign-in (needs a native SDK build), the installation-offer flow for products sold only with an installation
(the app sends the person to the website for those), and the partner / seller / admin dashboards (they stay on the website).

## Run it

```bash
cd apps/mobile
npm install
EXPO_PUBLIC_API_URL=http://<your-computer-ip>:8000/api/v1 npx expo start
```

`EXPO_PUBLIC_API_URL` defaults to `http://10.0.2.2:8000/api/v1`, which reaches a platform on your computer from the Android emulator.
Other settings (all optional):

| variable | what it is |
| --- | --- |
| `EXPO_PUBLIC_WEB_URL` | the website (account creation, password reset) |
| `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID`, `EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID` | Google client ids of the app; empty hides the Google button |

Push notifications and "Continue with Apple" need a development build (`npx expo run:ios|android` or `eas build --profile development`), not Expo Go.

## What the platform needs (apps/api `.env`)

```
EXPO_PUSH_ENABLED=true            # send push notifications through Expo
EXPO_ACCESS_TOKEN=                # optional, from your Expo account (extra security on the push service)
GOOGLE_MOBILE_CLIENT_IDS=ios-id,android-id   # the app's Google client ids (accepted besides the website's)
APPLE_MOBILE_CLIENT_IDS=com.armorhub.app     # the app's bundle id, for "Continue with Apple"
```

Every notification a person receives in the website's inbox is also pushed to their phones. The phone registers with `POST /api/v1/me/devices`
after sign-in and removes itself on sign-out.

## Publishing

1. Apple Developer account (99 USD a year) and Google Play Console (25 USD once).
2. `npx eas-cli@latest init`, then put the project id in `app.json` (`extra.eas.projectId`): push notifications need it.
3. Set the bundle id / package (`com.armorhub.app`) to the one you register, fill the store listings and privacy answers.
4. `eas build --platform all`, then `eas submit`.

Store rules worth knowing before the first submission:

- **Sign in with Apple** is required on iOS when other social sign-ins are offered (it is offered).
- **Account deletion** must be possible inside the app (it is: Account, then Delete my account).
- **Subscriptions for businesses** (seller / centre plans) are digital services: they are bought on the website only, never inside the iOS app.
- Goods and services ordered by customers are physical and exempt from in-app purchase rules.

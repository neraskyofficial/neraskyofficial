/**
 * Nerasky authentication UI: Google OAuth and the Supabase confirmation callback.
 * The PHP backend validates the Supabase access token before creating a session.
 */
(function () {
  "use strict";

  var config = window.NERASKY_SUPABASE_CONFIG || null;
  if (!config || !config.url || !config.key) return;

  var clientPromise;
  function loadClient() {
    if (!clientPromise) {
      var version = config.version || "2.57.4";
      clientPromise = import("https://esm.sh/@supabase/supabase-js@" + version)
        .then(function (module) {
          return module.createClient(config.url, config.key, {
            auth: {
              flowType: "pkce",
              detectSessionInUrl: true,
              persistSession: true,
              autoRefreshToken: false
            }
          });
        });
    }
    return clientPromise;
  }

  function showStatus(message) {
    var status = document.querySelector("[data-callback-status]");
    if (status) status.textContent = message;
  }

  function finishWithError(message) {
    window.location.replace(
      config.loginUrl + "?google_error=" + encodeURIComponent(message)
    );
  }

  function sendSession(client, next) {
    return client.auth.getSession().then(function (response) {
      if (response.error) throw response.error;
      var session = response.data && response.data.session;
      if (!session || !session.access_token) {
        throw new Error("No Supabase session was returned. Please try again.");
      }

      var body = new URLSearchParams();
      body.append("access_token", session.access_token);
      body.append("csrf", config.csrf || "");
      body.append("next", next || "dashboard.php");
      return fetch(config.endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        credentials: "same-origin",
        body: body.toString()
      }).then(function (response) {
        return response.json().then(function (result) {
          if (!response.ok || !result || !result.ok || !result.redirect) {
            throw new Error((result && result.message) || "Could not finish signing in.");
          }
          return client.auth.signOut({ scope: "local" }).catch(function (error) {
            console.warn("Could not clear the browser Supabase session.", error);
          }).then(function () {
            window.location.replace(result.redirect);
          });
        });
      });
    });
  }

  function wireGoogle(client) {
    Array.prototype.forEach.call(
      document.querySelectorAll("[data-supabase-google]"),
      function (button) {
        if (button.dataset.bound) return;
        button.dataset.bound = "1";
        button.addEventListener("click", function () {
          if (button.disabled) return;
          button.disabled = true;
          button.classList.add("loading");
          var label = button.querySelector("span");
          if (label) label.textContent = "Connecting to Google…";

          var next = button.getAttribute("data-next") || "dashboard.php";
          try {
            window.sessionStorage.setItem("nerasky-supabase-next", next);
          } catch (error) {
            console.warn("Could not store the post-sign-in destination.", error);
          }

          client.auth.signInWithOAuth({
            provider: "google",
            options: {
              redirectTo: config.callbackUrl,
              queryParams: { prompt: "select_account" }
            }
          }).then(function (response) {
            if (response.error) throw response.error;
          }).catch(function (error) {
            console.error("Supabase Google sign-in failed", error);
            button.disabled = false;
            button.classList.remove("loading");
            if (label) label.textContent = "Could not sign in — try again";
          });
        });
      }
    );
  }

  function handleCallback(client) {
    var mount = document.getElementById("supabaseCallback");
    if (!mount) return;

    showStatus("Confirming your Supabase account. This should only take a moment.");
    var next = "dashboard.php";
    try {
      next = window.sessionStorage.getItem("nerasky-supabase-next") || next;
      window.sessionStorage.removeItem("nerasky-supabase-next");
    } catch (error) {
      console.warn("Could not read the post-sign-in destination.", error);
    }

    sendSession(client, next).catch(function (error) {
      console.error("Supabase callback failed", error);
      finishWithError(error && error.message ? error.message : "Could not finish signing in.");
    });
  }

  loadClient().then(function (client) {
    wireGoogle(client);
    handleCallback(client);
  }).catch(function (error) {
    console.error("Could not initialize Supabase Auth", error);
    if (document.getElementById("supabaseCallback")) {
      finishWithError("Could not connect to Supabase. Check your configuration and try again.");
    }
    Array.prototype.forEach.call(document.querySelectorAll("[data-supabase-google]"), function (button) {
      button.disabled = false;
      button.classList.remove("loading");
      var label = button.querySelector("span");
      if (label) label.textContent = "Could not connect — try again";
    });
  });
})();

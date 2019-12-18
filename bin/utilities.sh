#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Utility functions to aid with setting up a local installation.

#######################################
# Ask a question of the user.
# Arguments:
#   QUESTION Question to display to and ask of the user.
# Returns:
#   Answer to the question. One of: [y, n]
#######################################
ask_yes_no() {
	QUESTION=$1

	while :
	do
        read -r -p $'\e[33m'"❔: $QUESTION"$' [y/N]: \e[0m' SHOULD_DO

		# Ensure that variable is caught and parsed correctly.
	    SHOULD_DO_ANSWER=$(echo "$SHOULD_DO" | tr '[:upper:]' '[:lower:]')

		# Evaluate output
		case "$SHOULD_DO_ANSWER" in
			y|"yes")
				echo "y"
				return
				;;
			n|"no"|"")
				echo "n"
				return
				;;
			*)
				echo "Please answer yes or no."
				;;
		esac
	done
}

#######################################
# Print a friendly message to the screen.
# Arguments:
#   MESSAGE Message to display
# Returns:
#   None
#######################################
print_message() {
	MESSAGE=$1

	tput setaf 2; echo $'\n' "$MESSAGE" $'\n'; tput sgr0
}

#######################################
# Print an error message to the screen.
# Arguments:
#   MESSAGE Message to display
# Returns:
#   None
#######################################
print_error() {
	MESSAGE=$1

	tput setaf 1; echo $'\n' "$MESSAGE" $'\n'; tput sgr0
}

#######################################
# Open a URL using methods available on the system
# Arguments:
#   URL to open
# Returns:
#   None
#######################################
openurl() {
	if hash xdg-open 2>/dev/null; then
		xdg-open "$@"
	else
		open "$@"
	fi
}

#######################################
# Parse the value of env variable in .env file
# Arguments:
#   Variable to parse
# Returns:
#   Variable value
#######################################
getenv() {
  variable=$1

	if [ -z "$(grep $variable .env)" ]; then
		echo ''
	else
		echo $(grep "^$variable" .env | cut -d '=' -f2)
	fi
}

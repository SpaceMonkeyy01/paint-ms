export default function ApplicationLogo(props) {
    // paint droplet
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2.2c.3 0 .5.1.7.3 1.6 2 3.2 3.9 4.4 5.8 1.2 1.9 2.1 3.8 2.1 5.7a7.2 7.2 0 1 1-14.4 0c0-1.9.9-3.8 2.1-5.7C8.1 6.4 9.7 4.5 11.3 2.5c.2-.2.4-.3.7-.3z" />
            <path
                d="M8.6 14.4a3.5 3.5 0 0 0 2.9 3.4"
                fill="none"
                stroke="#fff"
                strokeWidth="1.6"
                strokeLinecap="round"
                opacity=".85"
            />
        </svg>
    );
}

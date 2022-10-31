# customphppostgres

Add an .env file and add single variable there named 
domain=test.local.com
You can use this file to add more environment variables


Create default db name test and create table

```
CREATE TABLE public.users (
    id integer DEFAULT nextval('public.user_id_seq'::regclass) NOT NULL,
    firstname character varying(40) NOT NULL,
    lastname character varying(40) NOT NULL,
    email character varying(255) NOT NULL,
    created_at date DEFAULT CURRENT_DATE NOT NULL
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users email_unq; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT email_unq UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


